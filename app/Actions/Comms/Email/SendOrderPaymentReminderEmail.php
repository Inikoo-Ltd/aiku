<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithOrderingCustomerNotification;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * A trade customer who chose to pay by bank transfer is not chased the day they order: they said
 * they would pay later. RemindUnpaidBankTransferOrders sends this every few days, and once more
 * the day before the order is cancelled (INI-163). Each reminder carries the shop's bank details,
 * the same ones the order confirmation showed, so the customer can pay from the email.
 */
class SendOrderPaymentReminderEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithOrderingCustomerNotification;

    public const int REMINDER = 1;
    public const int FINAL_REMINDER = 2;

    public function handle(int $orderID, int $reminder = self::REMINDER): ?DispatchedEmail
    {
        $order = Order::find($orderID);
        if (!$order || $order->shop->type !== ShopTypeEnum::B2B) {
            return null;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($order->shop->language->code);

        list($emailHtmlBody, $dispatchedEmail) = $this->getEmailBody(
            $order->customer,
            OutboxCodeEnum::ORDER_PAYMENT_REMINDER
        );

        if (!$dispatchedEmail) {
            app()->setLocale($previousLocale);

            return null;
        }

        $outbox = $dispatchedEmail->outbox;

        if ($outbox->builder == OutboxBuilderEnum::BLADE) {
            $emailHtmlBody = Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template');
        }

        if (!$emailHtmlBody) {
            app()->setLocale($previousLocale);

            return null;
        }
        $order->dispatchedEmails()->attach($dispatchedEmail, ['outbox_id' => $outbox->id]);

        $subject = $outbox->emailOngoingRun?->email?->subject;
        if ($subject) {
            $subject = str_replace('[Order Number]', $order->reference, $subject);
        }

        $result = $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $subject,
            $emailHtmlBody,
            '',
            additionalData: [
                'customer_name'   => $order->customer->name,
                'shop_name'       => $order->shop->name,
                'email_body'      => $this->generateBodyHtml($order, $reminder),
                'order_link'      => $this->orderLink($order),
                'order_reference' => $order->reference,
            ],
            senderName: $outbox->emailOngoingRun->senderName(),
        );

        app()->setLocale($previousLocale);

        /** Only an order whose final reminder actually went out may be cancelled */
        if ($reminder === self::FINAL_REMINDER && in_array($result?->state, [DispatchedEmailStateEnum::SENT, DispatchedEmailStateEnum::DELIVERED], true)) {
            $order->update(['data' => array_merge((array) $order->refresh()->data, ['final_payment_reminder_at' => now()->toIso8601String()])]);
        }

        return $result;
    }

    /**
     * What the customer reads, for the shop settings: rendered by the code that sends it, on an
     * example order that is never saved.
     */
    public function previewHtml(Shop $shop, int $reminder): string
    {
        $daysAgo = $reminder === self::FINAL_REMINDER
            ? $shop->unpaidBankOrderSetting('cancel_after_days') - 1
            : $shop->unpaidBankOrderSetting('reminder_every_days');

        $order = (new Order())->forceFill([
            'reference'      => 'EXAMPLE-1001',
            'slug'           => 'example-1001',
            'total_amount'   => 432.10,
            'payment_amount' => 0,
            'submitted_at'   => now()->subDays($daysAgo),
            'data'           => [],
        ]);
        $order->setRelation('shop', $shop);
        $order->setRelation('currency', $shop->currency);
        $order->setRelation('customer', (new Customer())->forceFill(['name' => 'Example Ltd']));

        $previousLocale = app()->getLocale();
        app()->setLocale($shop->language->code);
        $html = $this->generateBodyHtml($order, $reminder);
        app()->setLocale($previousLocale);

        return $html;
    }

    public function generateBodyHtml(Order $order, int $reminder = self::REMINDER): string
    {
        $amountDue = number_format(round($order->total_amount - $order->payment_amount, 2), 2);
        $paragraph = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #333; line-height: 1.6em; margin: 0 0 16px;"';

        $html = '<p '.$paragraph.'>'.__('Hello :name,', ['name' => $order->customer->name]).'</p>';

        $html .= '<p '.$paragraph.'>'.__(
            'We have not yet received your bank transfer for order :reference placed on :date.',
            ['reference' => $order->reference, 'date' => $this->formatDate($order->submitted_at)]
        );
        if ($reminder === self::FINAL_REMINDER) {
            $payBy = $this->formatDate($order->submitted_at?->copy()->addDays($order->shop->unpaidBankOrderSetting('cancel_after_days') - 1));
            $html  .= ' <strong>'.__('Please complete the payment by :date otherwise your order will be cancelled.', ['date' => $payBy]).'</strong>';
        }
        $html .= '</p>';

        $html .= '<p '.$paragraph.'>'.__('The amount outstanding is :currency :amount.', ['currency' => $order->currency->code, 'amount' => $amountDue]).'</p>';

        if ($reminder === self::REMINDER) {
            $html .= '<p '.$paragraph.'>'.__('Your order will be sent out as soon as the payment reaches us.').'</p>';
        }

        $html .= SendNewOrderEmailToCustomer::make()->generateOrderPaymentByBankTransferHtml($order);

        if ($orderLink = $this->orderLink($order)) {
            $html .= '<p style="margin: 24px 0;"><a href="'.$orderLink.'" '
                .'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #1f2937; background-color: #f3f4f6; '
                .'border: 1px solid #d1d5db; text-decoration: none; padding: 10px 18px; border-radius: 4px; display: inline-block;">'
                .__('View the order').'</a></p>';
        }

        $html .= '<p '.$paragraph.'>'.__('If you have already paid, thank you, please ignore this email.').'</p>';

        return $html;
    }

    /** Carbon formats an unknown locale as an empty string, which would leave "pay by" with no date */
    protected function formatDate(?Carbon $date): string
    {
        if (!$date) {
            return '';
        }

        return $date->copy()->locale(app()->getLocale())->isoFormat('LL') ?: $date->format('j M Y');
    }

    /** A shop without a website has nowhere to link to; the email still has to go out. */
    protected function orderLink(Order $order): ?string
    {
        return $order->shop->website ? $this->getOrderLink($order) : null;
    }
}
