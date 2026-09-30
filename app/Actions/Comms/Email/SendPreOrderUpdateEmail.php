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
        $reference = $preOrder->order->reference;

        return match ($type) {
            self::BALANCE_REQUEST => __('Your pre-order :reference has arrived: balance due', ['reference' => $reference]),
            self::BALANCE_REMINDER => __('Reminder: balance due for pre-order :reference', ['reference' => $reference]),
            self::DISPATCH_DATE_CHANGED => __('New estimated dispatch for pre-order :reference', ['reference' => $reference]),
            self::CANCELLED => __('Pre-order :reference cancelled', ['reference' => $reference]),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function body(PreOrder $preOrder, string $type, array $context = []): string
    {
        $order     = $preOrder->order;
        $shop      = $order->shop;
        $currency  = $order->currency->code;
        $amountDue = number_format(max(0, round((float) $order->total_amount - (float) $order->payment_amount, 2)), 2);
        $dueDate   = $preOrder->balance_due_at?->toFormattedDateString();
        $paragraph = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #333; line-height: 1.6em; margin: 0 0 16px;"';

        $paragraphs = [__('Hello :name,', ['name' => $order->customer->name])];

        switch ($type) {
            case self::BALANCE_REQUEST:
                $paragraphs[] = __('Good news: the goods for your pre-order :reference have reached our warehouse.', ['reference' => $order->reference]);
                if ($preOrder->pallet_quote_amount !== null) {
                    $paragraphs[] = __('The pallet delivery costs :currency :quote (the estimate was :currency :estimate).', [
                        'currency' => $currency,
                        'quote'    => number_format((float) $preOrder->pallet_quote_amount, 2),
                        'estimate' => number_format((float) $preOrder->pallet_estimate_amount, 2),
                    ]);
                    if ($this->isPalletQuoteOverTolerance($preOrder)) {
                        $paragraphs[] = __('As this is more than :percentage% above the estimate, you can cancel the order and get your deposit back. Just reply to this email.', [
                            'percentage' => $shop->preOrderSetting('pallet_quote_tolerance_percentage'),
                        ]);
                    }
                }
                $paragraphs[] = __('The balance of :currency :amount is due by :date. Please pay it with the link below and we will send your order.', [
                    'currency' => $currency,
                    'amount'   => $amountDue,
                    'date'     => $dueDate,
                ]);
                $paragraphs[] = __('If it is not paid within :days days of this email, the order is cancelled and the deposit is kept.', [
                    'days' => $shop->preOrderSetting('balance_cancel_after_days'),
                ]);
                break;
            case self::BALANCE_REMINDER:
                $paragraphs[] = __('The balance of :currency :amount for your pre-order :reference is due by :date.', [
                    'currency'  => $currency,
                    'amount'    => $amountDue,
                    'reference' => $order->reference,
                    'date'      => $dueDate,
                ]);
                $paragraphs[] = __('If it is not paid by :date, the order is cancelled and the deposit is kept.', [
                    'date' => $preOrder->balance_requested_at?->copy()->addDays((int) $shop->preOrderSetting('balance_cancel_after_days'))->toFormattedDateString(),
                ]);
                break;
            case self::DISPATCH_DATE_CHANGED:
                $paragraphs[] = __('The estimated dispatch of your pre-order :reference has changed. It is now expected between :from and :to.', [
                    'reference' => $order->reference,
                    'from'      => $preOrder->estimated_dispatch_from?->toFormattedDateString(),
                    'to'        => $preOrder->estimated_dispatch_to?->toFormattedDateString(),
                ]);
                if ($reason = Arr::get($context, 'reason')) {
                    $paragraphs[] = $reason;
                }
                $paragraphs[] = __('You can cancel the order from your account for a refund as set out in the pre-order terms. If we are more than :days days past the estimated dispatch, you get a full refund, deposit included.', [
                    'days' => $shop->preOrderSetting('late_cancellation_days'),
                ]);
                break;
            case self::CANCELLED:
                $paragraphs[] = __('Your pre-order :reference has been cancelled.', ['reference' => $order->reference]);
                if ($reason = Arr::get($context, 'reason')) {
                    $paragraphs[] = $reason;
                }
                $refund = (float) Arr::get($context, 'refund_amount', 0);
                $paragraphs[] = $refund > 0
                    ? __(':currency :amount has been returned to your account balance.', ['currency' => $currency, 'amount' => number_format($refund, 2)])
                    : __('No refund is due under the pre-order terms.');
                break;
            default:
                break;
        }

        $html = implode('', array_map(fn ($text) => '<p '.$paragraph.'>'.e($text).'</p>', $paragraphs));

        if ($type != self::CANCELLED && $orderLink = $this->orderLink($preOrder)) {
            $html .= '<p style="margin: 0 0 24px;"><a href="'.$orderLink.'" '
                .'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #fff; background-color: #4f46e5; '
                .'text-decoration: none; padding: 12px 20px; border-radius: 4px; display: inline-block;">'
                .e(in_array($type, [self::BALANCE_REQUEST, self::BALANCE_REMINDER]) ? __('Pay the balance') : __('View the order')).'</a></p>';
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
