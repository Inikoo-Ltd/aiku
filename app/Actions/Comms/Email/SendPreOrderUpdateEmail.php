<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithOrderingCustomerNotification;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Ordering\PreOrder\GetPreOrderText;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Ordering\PreOrder;
use Illuminate\Support\Arr;
use Sentry;

/**
 * Every email about a pre-order after its confirmation (HELP-3432): the balance request when the
 * goods arrive, its reminders, a new dispatch date, and a cancellation. One blade outbox with the
 * prose built here under the shop's locale, as SendChannelOrderOnHoldEmail does.
 */
class SendPreOrderUpdateEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithOrderingCustomerNotification;

    public const string BALANCE_REQUEST = 'balance_request';
    public const string BALANCE_REMINDER = 'balance_reminder';
    public const string DISPATCH_DATE_CHANGED = 'dispatch_date_changed';
    public const string CANCELLED = 'cancelled';

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(PreOrder $preOrder, string $type, array $context = []): ?DispatchedEmail
    {
        $order = $preOrder->order;
        if (!$order->shop->outboxes()->where('code', OutboxCodeEnum::PRE_ORDER_UPDATE)->exists()) {
            Sentry::captureMessage('Pre-order email '.$type.' not sent for order '.$order->id.': shop has no pre_order_update outbox');

            return null;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($order->shop->language->code);

        try {
            list($emailHtmlBody, $dispatchedEmail) = $this->getEmailBody($order->customer, OutboxCodeEnum::PRE_ORDER_UPDATE);
            if (!$dispatchedEmail) {
                return null;
            }

            $outbox = $dispatchedEmail->outbox;
            if ($outbox->builder == OutboxBuilderEnum::BLADE) {
                $emailHtmlBody = Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template');
            }
            if (!$emailHtmlBody) {
                return null;
            }

            $order->dispatchedEmails()->attach($dispatchedEmail, ['outbox_id' => $outbox->id]);

            return $this->sendEmailWithMergeTags(
                $dispatchedEmail,
                $outbox->emailOngoingRun->sender(),
                $this->subject($preOrder, $type),
                $emailHtmlBody,
                '',
                additionalData: [
                    'customer_name'   => $order->customer->name,
                    'shop_name'       => $order->shop->name,
                    'email_body'      => $this->body($preOrder, $type, $context),
                    'order_reference' => $order->reference,
                ],
                senderName: $outbox->emailOngoingRun->senderName(),
            );
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    public function subject(PreOrder $preOrder, string $type): string
    {
        $key = match ($type) {
            self::BALANCE_REQUEST => 'email_balance_request_subject',
            self::BALANCE_REMINDER => 'email_balance_reminder_subject',
            self::DISPATCH_DATE_CHANGED => 'email_dispatch_changed_subject',
            self::CANCELLED => 'email_cancelled_subject',
        };

        return GetPreOrderText::make()->handle($preOrder->order->shop, $key, ['order_number' => $preOrder->order->reference]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function body(PreOrder $preOrder, string $type, array $context = []): string
    {
        $order     = $preOrder->order;
        $shop      = $order->shop;
        $texts     = GetPreOrderText::make();
        $currency  = $order->currency->code;
        $paragraph = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #333; line-height: 1.6em; margin: 0 0 16px;"';

        $values = [
            'customer_name'    => $order->customer->name,
            'order_number'     => $order->reference,
            'amount'           => $currency.' '.number_format(max(0, round((float) $order->total_amount - (float) $order->payment_amount, 2)), 2),
            'balance_due_date' => $preOrder->balance_due_at?->format('d/m/Y'),
            'cancel_date'      => $preOrder->balance_requested_at?->copy()->addDays((int) $shop->preOrderSetting('balance_cancel_after_days'))->format('d/m/Y'),
            'from_date'        => $preOrder->estimated_dispatch_from?->format('d/m/Y'),
            'to_date'          => $preOrder->estimated_dispatch_to?->format('d/m/Y'),
            'pallet_quote'     => $currency.' '.number_format((float) $preOrder->pallet_quote_amount, 2),
            'pallet_estimate'  => $currency.' '.number_format((float) $preOrder->pallet_estimate_amount, 2),
        ];

        $paragraphs = [$texts->handle($shop, 'email_greeting', $values)];

        switch ($type) {
            case self::BALANCE_REQUEST:
                $requestParagraphs = $texts->paragraphs($shop, 'email_balance_request_body', $values);
                $palletParagraphs  = [];
                if ($preOrder->pallet_quote_amount !== null) {
                    $palletParagraphs[] = $texts->handle($shop, 'email_pallet_quote', $values);
                    if ($this->isPalletQuoteOverTolerance($preOrder)) {
                        $palletParagraphs[] = $texts->handle($shop, 'email_pallet_over_tolerance', $values);
                    }
                }
                array_splice($requestParagraphs, 1, 0, $palletParagraphs);
                $paragraphs = array_merge($paragraphs, $requestParagraphs);
                break;
            case self::BALANCE_REMINDER:
                $paragraphs = array_merge($paragraphs, $texts->paragraphs($shop, 'email_balance_reminder_body', $values));
                break;
            case self::DISPATCH_DATE_CHANGED:
                $changedParagraphs = $texts->paragraphs($shop, 'email_dispatch_changed_body', $values);
                if ($reason = Arr::get($context, 'reason')) {
                    array_splice($changedParagraphs, 1, 0, [$reason]);
                }
                $paragraphs = array_merge($paragraphs, $changedParagraphs);
                break;
            case self::CANCELLED:
                $paragraphs = array_merge($paragraphs, $texts->paragraphs($shop, 'email_cancelled_body', $values));
                if ($reason = Arr::get($context, 'reason')) {
                    $paragraphs[] = $reason;
                }
                $refund       = (float) Arr::get($context, 'refund_amount', 0);
                $paragraphs[] = $refund > 0
                    ? $texts->handle($shop, 'email_cancelled_refund', array_merge($values, ['amount' => $currency.' '.number_format($refund, 2)]))
                    : $texts->handle($shop, 'email_cancelled_no_refund', $values);
                break;
            default:
                break;
        }

        $html = implode('', array_map(fn ($text) => '<p '.$paragraph.'>'.nl2br(e($text)).'</p>', $paragraphs));

        if ($type != self::CANCELLED && $orderLink = $this->orderLink($preOrder)) {
            $isBalanceEmail = in_array($type, [self::BALANCE_REQUEST, self::BALANCE_REMINDER]);
            if ($isBalanceEmail && $preOrder->is_trade) {
                $orderLink .= '/pay-balance';
            }
            $html .= '<p style="margin: 0 0 24px;"><a href="'.$orderLink.'" '
                .'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #fff; background-color: #4f46e5; '
                .'text-decoration: none; padding: 12px 20px; border-radius: 4px; display: inline-block;">'
                .e($texts->handle($shop, $isBalanceEmail ? 'email_pay_button' : 'email_view_button')).'</a></p>';
        }

        return $html;
    }

    public function isPalletQuoteOverTolerance(PreOrder $preOrder): bool
    {
        if ($preOrder->pallet_quote_amount === null || !(float) $preOrder->pallet_estimate_amount) {
            return false;
        }

        $tolerance = (float) $preOrder->shop->preOrderSetting('pallet_quote_tolerance_percentage');

        return (float) $preOrder->pallet_quote_amount > (float) $preOrder->pallet_estimate_amount * (1 + $tolerance / 100);
    }

    private function orderLink(PreOrder $preOrder): ?string
    {
        try {
            return $preOrder->order->shop->website ? $this->getOrderLink($preOrder->order) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
