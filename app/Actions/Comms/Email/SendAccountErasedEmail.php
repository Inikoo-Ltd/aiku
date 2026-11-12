<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * Tells the customer their account and personal data were erased (HELP-4652). It has to go out
 * before AnonymiseCustomer scrubs the address, because afterwards nobody can reach them, which is
 * what they asked for. A failure here is reported and never stops the erasure.
 */
class SendAccountErasedEmail
{
    use AsAction;
    use WithSendBulkEmails;

    public function handle(Customer $customer): ?DispatchedEmail
    {
        if (!$customer->email) {
            return null;
        }

        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', OutboxCodeEnum::ACCOUNT_ERASED)->first();
        if (!$outbox || $outbox->state != OutboxStateEnum::ACTIVE) {
            return null;
        }

        $emailHtmlBody = Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template');
        if (!$emailHtmlBody) {
            return null;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($customer->shop->language->code);

        try {
            $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $customer, [
                'outbox_id'     => $outbox->id,
                'email_address' => $customer->email,
            ]);

            return $this->sendEmailWithMergeTags(
                $dispatchedEmail->refresh(),
                $outbox->emailOngoingRun->sender(),
                __('Your :shop account has been deleted', ['shop' => $customer->shop->name]),
                $emailHtmlBody,
                '',
                additionalData: [
                    'customer_name' => $customer->name,
                    'shop_name'     => $customer->shop->name,
                    'email_body'    => $this->generateBodyHtml($customer),
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

    public function generateBodyHtml(Customer $customer): string
    {
        $paragraph = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #333; line-height: 1.6em; margin: 0 0 16px;"';

        return '<p '.$paragraph.'>'.__('Hello :name,', ['name' => $customer->contact_name ?: $customer->name]).'</p>'
            .'<p '.$paragraph.'>'.__('As you asked, your :shop account has been deleted and your personal details have been erased. You will not receive any more emails from us.', ['shop' => $customer->shop->name]).'</p>'
            .'<p '.$paragraph.'>'.__('If you placed orders with us, we keep their invoices because the law requires it.').'</p>'
            .'<p '.$paragraph.'>'.__('Thank you for having been our customer.').'</p>';
    }
}
