<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\CRM\CustomerClaim\GetCustomerClaimForm;
use App\Actions\CRM\CustomerClaim\RejectCustomerClaim;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimSourceEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * Tells the customer where their claim is (HELP-4869): received and waiting for our team, approved
 * with its outcome, or rejected with the reason. Only claims customers made themselves are emailed;
 * staff and backfilled claims are records of what staff already told the customer. A failure is
 * reported and never undoes the claim.
 */
class SendCustomerClaimEmail
{
    use AsAction;
    use WithSendBulkEmails;

    public function handle(CustomerClaim $claim): ?DispatchedEmail
    {
        $customer = $claim->customer;
        if ($claim->source === CustomerClaimSourceEnum::STAFF || !$customer?->email) {
            return null;
        }

        /** @var Outbox $outbox */
        $outbox = $claim->shop->outboxes()->where('code', OutboxCodeEnum::CUSTOMER_CLAIM_UPDATE)->first();
        if (!$outbox || $outbox->state != OutboxStateEnum::ACTIVE) {
            return null;
        }

        $emailHtmlBody = Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template');
        if (!$emailHtmlBody) {
            return null;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($claim->shop->language->code);

        try {
            $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $customer, [
                'outbox_id'     => $outbox->id,
                'email_address' => $customer->email,
            ]);

            return $this->sendEmailWithMergeTags(
                $dispatchedEmail->refresh(),
                $outbox->emailOngoingRun->sender(),
                $this->subject($claim),
                $emailHtmlBody,
                '',
                additionalData: [
                    'customer_name' => $customer->name,
                    'shop_name'     => $claim->shop->name,
                    'email_body'    => $this->generateBodyHtml($claim),
                ],
                senderName: $outbox->emailOngoingRun->senderName(),
            );
        } catch (Throwable $exception) {
            Sentry::captureException($exception);

            return null;
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    public function subject(CustomerClaim $claim): string
    {
        return match ($claim->state) {
            CustomerClaimStateEnum::SETTLED  => __('Your claim :reference is approved', ['reference' => $claim->reference]),
            CustomerClaimStateEnum::REJECTED => __('About your claim :reference', ['reference' => $claim->reference]),
            default                          => __('We have received your claim :reference', ['reference' => $claim->reference]),
        };
    }

    public function generateBodyHtml(CustomerClaim $claim): string
    {
        $paragraph = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #333; line-height: 1.6em; margin: 0 0 16px;"';
        $cell      = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 13px; color: #333; padding: 4px 8px 4px 0; border-bottom: 1px solid #eee; vertical-align: top;"';
        $replace   = ['reference' => e($claim->reference), 'order' => e((string) $claim->order?->reference)];

        $paragraphs = match ($claim->state) {
            CustomerClaimStateEnum::SETTLED => [
                __('Your claim :reference for order :order is approved.', $replace),
                match ($claim->resolution) {
                    'refund_to_balance' => __(':amount has been added to your balance.', ['amount' => Number::currency((float) $claim->amount, (string) $claim->currency_code, app()->getLocale())]),
                    'replacement'       => __('We are sending you a replacement.'),
                    'next_order'        => __('The items will be added free to your next order.'),
                    default             => null,
                },
            ],
            CustomerClaimStateEnum::REJECTED => [
                __('We could not accept your claim :reference for order :order.', $replace),
                __('Reason: :reason', ['reason' => e(RejectCustomerClaim::reasonLabel((string) $claim->rejection_reason))]),
                $claim->rejection_note ? nl2br(e($claim->rejection_note)) : null,
                __('If you think this is wrong, reply to this email and our team will look at it again.'),
            ],
            default => [
                __('We have received your claim :reference for order :order.', $replace),
                __('Our team will check it and reply to you by email.'),
            ],
        };

        $rows = $claim->lines->map(fn ($line) => '<tr><td '.$cell.'>'.e($line->name ?: $line->code).'</td><td '.$cell.'>× '.(float) $line->quantity.'</td><td '.$cell.'>'.e(GetCustomerClaimForm::reasonLabel($line->reason, app()->getLocale())).'</td></tr>')->implode('');

        return '<p '.$paragraph.'>'.__('Hello :name,', ['name' => e($claim->customer->contact_name ?: $claim->customer->name)]).'</p>'
            .collect($paragraphs)->filter()->map(fn (string $text) => '<p '.$paragraph.'>'.$text.'</p>')->implode('')
            .'<table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">'.$rows.'</table>';
    }
}
