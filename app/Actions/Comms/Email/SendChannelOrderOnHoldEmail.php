<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithOrderingCustomerNotification;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\Ordering\Order\WithOrderForbiddenCountryCheck;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use App\Models\Dropshipping\Platform;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * An order that arrives from a platform is one the customer never watched us try to charge. When the
 * money cannot be taken it stops, and without this email the first they know of it is a buyer asking
 * where the parcel is (HELP-3116).
 *
 * The wording deliberately does not say why. Nine out of ten of these are channels that were
 * authenticated but never got as far as saving a card, so nothing was ever attempted and telling
 * that customer their payment failed would be a lie; the rest are ordinary declines. Both need the
 * same thing done about them, so the email asks for that rather than diagnosing. The exception is an
 * order to a country the shop does not deliver to, which no card would send out, so that one says why.
 */
class SendChannelOrderOnHoldEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithOrderingCustomerNotification;
    use WithOrderForbiddenCountryCheck;

    public const int NOTICE = 0;
    public const int REMINDER = 1;
    public const int FINAL_REMINDER = 2;

    public function handle(int $orderID, int $reminder = self::NOTICE): ?DispatchedEmail
    {
        $order = Order::find($orderID);
        if (!$order || $order->shop->type !== ShopTypeEnum::DROPSHIPPING) {
            return null;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($order->shop->language->code);

        list($emailHtmlBody, $dispatchedEmail) = $this->getEmailBody(
            $order->customer,
            OutboxCodeEnum::CHANNEL_ORDER_ON_HOLD
        );

        if (!$dispatchedEmail) {
            app()->setLocale($previousLocale);

            return null;
        }

        $outbox = $dispatchedEmail->outbox;

        /** A blade outbox keeps its HTML in the snapshot layout, not in compiled_layout, which is
         * only filled for the drag and drop builders; the same switch every blade sender makes. */
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
                'customer_name' => $order->customer->name,
                'shop_name'     => $order->shop->name,
                'email_body'    => $this->generateBodyHtml($order, $reminder),
                'order_link'    => $this->orderLink($order),
                'order_reference' => $order->reference,
            ],
            senderName: $outbox->emailOngoingRun->senderName(),
        );

        app()->setLocale($previousLocale);

        /** Only an order whose final reminder actually went out may be cancelled (HELP-4426) */
        if ($reminder === self::FINAL_REMINDER && in_array($result?->state, [DispatchedEmailStateEnum::SENT, DispatchedEmailStateEnum::DELIVERED], true)) {
            $order->update(['data' => array_merge((array) $order->refresh()->data, ['final_payment_reminder_at' => now()->toIso8601String()])]);
        }

        return $result;
    }

    /**
     * What the customer reads, for the shop settings (HELP-4426): the wording is not editable, so
     * staff see it rendered by the same code that sends it, on an example order that is never saved.
     */
    public function previewHtml(Shop $shop, int $reminder): string
    {
        $order = (new Order())->forceFill([
            'reference'      => 'EXAMPLE-1001',
            'slug'           => 'example-1001',
            'total_amount'   => 32.21,
            'payment_amount' => 0,
            'submitted_at'   => now()->subDays($reminder === self::NOTICE ? 0 : $shop->unpaidChannelOrderSetting($reminder === self::REMINDER ? 'reminder_day' : 'final_reminder_day')),
            'data'           => [],
        ]);
        $order->setRelation('shop', $shop);
        $order->setRelation('currency', $shop->currency);
        $order->setRelation('customer', (new Customer())->forceFill(['name' => 'Example Ltd']));
        $order->setRelation('platform', (new Platform())->forceFill(['name' => 'Shopify']));
        $order->setRelation('customerSalesChannel', (new CustomerSalesChannel())->forceFill(['slug' => 'example']));

        $previousLocale = app()->getLocale();
        app()->setLocale($shop->language->code);
        $html = $this->generateBodyHtml($order, $reminder);
        app()->setLocale($previousLocale);

        return $html;
    }

    /**
     * The prose lives here rather than in the template because a template's compiled_layout is
     * frozen at seed time and could only ever be one language.
     */
    public function generateBodyHtml(Order $order, int $reminder = self::NOTICE): string
    {
        $amountDue = number_format(round($order->total_amount - $order->payment_amount, 2), 2);
        $currency  = $order->currency->code;
        $platform  = $order->platform?->name ?? __('your sales channel');

        $paragraph = 'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #333; line-height: 1.6em; margin: 0 0 16px;"';

        $html = '<p '.$paragraph.'>'.__('Hello :name,', ['name' => $order->customer->name]).'</p>';

        if ($bannedDelivery = data_get($order->data, 'banned_delivery')) {
            return $html.$this->generateBannedDeliveryBodyHtml($order, $platform, $this->bannedDeliveryReason($bannedDelivery), $paragraph);
        }

        if ($reminder !== self::NOTICE) {
            return $html.$this->generateReminderBodyHtml($order, $reminder, $currency, $amountDue, $paragraph);
        }

        $html .= '<p '.$paragraph.'>'.__(
            'Order :reference from :platform is on hold. We have not been able to take payment for it, so it is waiting and will not be sent out yet.',
            ['reference' => $order->reference, 'platform' => $platform]
        ).'</p>';

        $html .= '<p '.$paragraph.'>'.__(
            'The amount outstanding is :currency :amount.',
            ['currency' => $currency, 'amount' => $amountDue]
        ).'</p>';

        $html .= '<p '.$paragraph.'><strong>'.__(
            'We strongly recommend saving a payment card on your sales channel.'
        ).'</strong> '.__(
            'Once a card is saved, orders that come in from your channel are paid and sent out on their own, and none of them will wait like this one.'
        ).'</p>';

        $html .= $this->viewOrderButtonHtml($order);

        $html .= '<p '.$paragraph.'>'.__('If you believe this is a mistake, reply to this email and we will look into it.').'</p>';

        return $html;
    }

    /**
     * An order held because we do not deliver to its address was never offered for payment, so it
     * must not read as a payment problem: a saved card would not send it out (HELP-4392).
     */
    protected function generateBannedDeliveryBodyHtml(Order $order, string $platform, string $reason, string $paragraph): string
    {
        $html = '<p '.$paragraph.'>'.__(
            'Order :reference from :platform is on hold and will not be sent out. :reason',
            ['reference' => $order->reference, 'platform' => $platform, 'reason' => $reason]
        ).'</p>';

        $html .= '<p '.$paragraph.'>'.__('Please let your customer know and cancel the order on :platform.', ['platform' => $platform]).'</p>';

        $html .= $this->viewOrderButtonHtml($order);

        $html .= '<p '.$paragraph.'>'.__('If you believe this is a mistake, reply to this email and we will look into it.').'</p>';

        return $html;
    }

    /**
     * The wording of the reminders is CS's (HELP-4426); the final one names the last day to pay,
     * the day before RemindChannelOrdersOnHold cancels the order.
     */
    protected function generateReminderBodyHtml(Order $order, int $reminder, string $currency, string $amountDue, string $paragraph): string
    {
        $placedOn = $this->formatDate($order->submitted_at);

        $html = '<p '.$paragraph.'>'.__(
            'Your order :reference placed on :date is still on hold.',
            ['reference' => $order->reference, 'date' => $placedOn]
        );
        if ($reminder === self::FINAL_REMINDER) {
            $payBy = $this->formatDate($order->submitted_at?->copy()->addDays($order->shop->unpaidChannelOrderSetting('cancel_after_days') - 1));
            $html  .= ' <strong>'.__('Please complete the payment by :date otherwise your order will be cancelled.', ['date' => $payBy]).'</strong>';
        }
        $html .= '</p>';

        $html .= '<p '.$paragraph.'>'.__('The amount outstanding is :currency :amount.', ['currency' => $currency, 'amount' => $amountDue]).'</p>';

        if ($reminder === self::REMINDER) {
            $html .= '<p '.$paragraph.'>'.__('Please complete the payment.').'</p>';
        }

        if ($baseUrl = $this->retinaBaseUrl($order)) {
            $link = 'style="color: #333; text-decoration: underline;"';
            $html .= '<p '.$paragraph.'>'.__(
                'You can also :top_up or :update_card.',
                [
                    'top_up'      => '<a href="'.$baseUrl.'/app/top-up/dashboard" '.$link.'>'.__('top up your balance').'</a>',
                    'update_card' => '<a href="'.$baseUrl.'/app/dropshipping/saved-credit-cards" '.$link.'>'.__('update your card details').'</a>',
                ]
            ).'</p>';
        }

        return $html.$this->viewOrderButtonHtml($order);
    }

    /** Carbon formats an unknown locale as an empty string, which would leave "pay by" with no date */
    protected function formatDate(?Carbon $date): string
    {
        if (!$date) {
            return '';
        }

        return $date->copy()->locale(app()->getLocale())->isoFormat('LL') ?: $date->format('j M Y');
    }

    protected function retinaBaseUrl(Order $order): ?string
    {
        if (!$order->shop->website) {
            return null;
        }

        return app()->isLocal() ? 'https://ds.test' : 'https://'.$order->shop->website->domain;
    }

    protected function viewOrderButtonHtml(Order $order): string
    {
        if (!$orderLink = $this->orderLink($order)) {
            return '';
        }

        return '<p style="margin: 0 0 24px;"><a href="'.$orderLink.'" '
            .'style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; color: #1f2937; background-color: #f3f4f6; '
            .'border: 1px solid #d1d5db; text-decoration: none; padding: 10px 18px; border-radius: 4px; display: inline-block;">'
            .__('View the order').'</a></p>';
    }

    /** A shop without a website has nowhere to link to; the email still has to go out. */
    protected function orderLink(Order $order): ?string
    {
        return $order->shop->website ? $this->getOrderLink($order) : null;
    }
}
