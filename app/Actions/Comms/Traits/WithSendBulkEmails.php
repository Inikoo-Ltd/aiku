<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Dec 2023 13:11:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\Ses\SendSesEmail;
use App\Models\Comms\ChatEmailRecipient;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\ExternalSubscriberEmailRecipient;
use App\Models\Comms\TestEmailRecipient;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait WithSendBulkEmails
{
    use WithProcessEmailStyles;

    public function sendEmailWithMergeTags(
        DispatchedEmail $dispatchedEmail,
        string $sender,
        string $subject,
        string $emailHtmlBody,
        ?string $unsubscribeUrl = null,
        ?string $passwordToken = null,
        ?string $invoiceUrl = null,
        array $additionalData = [],
        ?string $senderName = null,
        bool $isTest = false,
        bool $debug = false
    ): DispatchedEmail {
        $html = $emailHtmlBody;
        // $html = $this->processStyles($html);

        // Remove MSO conditional comments temporarily
        // 's' flag = dot matches newline, 'u' flag = UTF-8 safe
        $msoComments = [];
        $html        = preg_replace_callback('/<!--\[if mso\]>.*?<!\[endif\]-->/su', function ($matches) use (&$msoComments) {
            $placeholder               = '___MSO_COMMENT_' . count($msoComments) . '___';
            $msoComments[$placeholder] = $matches[0];

            return $placeholder;
        }, $html);

        $mergeTagCache = [];
        $callback      = function ($matches) use ($dispatchedEmail, $unsubscribeUrl, $passwordToken, $invoiceUrl, $additionalData, &$mergeTagCache) {
            $fullTag    = $matches[0];
            $tagContent = $matches[1];

            if (!array_key_exists($fullTag, $mergeTagCache)) {
                $mergeTagCache[$fullTag] = (string)$this->replaceMergeTags($tagContent, $dispatchedEmail, $unsubscribeUrl, $passwordToken, $invoiceUrl, $additionalData);
            }

            return $mergeTagCache[$fullTag];
        };

        // 'u' flag ensures multi-byte UTF-8 characters are not corrupted
        $html = preg_replace_callback('/{{(.*?)}}/u', $callback, $html);
        $html = preg_replace_callback('/\[(.*?)]/u', $callback, $html);

        // Restore MSO conditional comments
        if ($msoComments) {
            $html = str_replace(array_keys($msoComments), array_values($msoComments), $html);
        }

        // 'u' flag ensures multi-byte UTF-8 characters are not corrupted
        $html = preg_replace('/\R+/u', '', $html);

        return SendSesEmail::run(
            subject: $subject,
            emailHtmlBody: $html,
            dispatchedEmail: $dispatchedEmail,
            sender: $sender,
            unsubscribeUrl: $unsubscribeUrl,
            senderName: $senderName,
            isTest: $isTest,
            debug: $debug
        );
    }

    private function replaceMergeTags($placeholder, $dispatchedEmail, ?string $unsubscribeUrl = null, ?string $passwordToken = null, ?string $invoiceUrl = null, array $additionalData = []): ?string
    {
        $originalPlaceholder = $placeholder;
        $placeholder         = Str::kebab(trim($placeholder));

        if ($placeholder === 'customer-name') {
            if (Arr::get($additionalData, 'customer_name')) {
                return Arr::get($additionalData, 'customer_name');
            }

            if ($webUserHasDispatchedEmail = $this->getWebUserDispatch($dispatchedEmail->id)) {
                $customerName = WebUser::find($webUserHasDispatchedEmail->web_user_id)?->customer?->name ?? '';
            } elseif ($userHasDispatchedEmail = $this->getUserDispatch($dispatchedEmail->id)) {
                $customerName = Arr::get($additionalData, 'customer_name') ?? User::find($userHasDispatchedEmail->user_id)?->contact_name ?? '';
            } elseif ($customerHasDispatchedEmail = $this->getCustomerDispatch($dispatchedEmail->id)) {
                $customerName = Customer::find($customerHasDispatchedEmail->customer_id)?->name ?? '';
            } elseif ($externalSubscriberHasDispatchedEmail = $this->getExternalSubscriberDispatch($dispatchedEmail->id)) {
                $customerName = ExternalSubscriberEmailRecipient::find($externalSubscriberHasDispatchedEmail->external_subscriber_email_recipient_id)?->name ?? '';
            } elseif ($testEmailRecipientHasDispatchedEmail = $this->getTestEmailRecipientDispatch($dispatchedEmail->id)) {
                $customerName = TestEmailRecipient::find($testEmailRecipientHasDispatchedEmail->test_email_recipient_id)?->name ?? '';
            } elseif ($chatEmailRecipientHasDispatchedEmail = $this->getChatEmailRecipientDispatch($dispatchedEmail->id)) {
                $customerName = ChatEmailRecipient::find($chatEmailRecipientHasDispatchedEmail->chat_email_recipient_id)?->name ?? '';
            } else {
                $customerName = Arr::get($additionalData, 'customer_name') ?? 'Customer name undefined';
            }

            return $customerName;
        }


        /** @noinspection HtmlUnknownAttribute */
        return match ($placeholder) {
            'username' => $this->getUsername($additionalData, $dispatchedEmail->id),
            'rejected-notes' => Arr::get($additionalData, 'rejected_notes'),
            'invoice_-url' => $invoiceUrl,
            'reset_-password_-u-r-l' => $passwordToken,
            'unsubscribe' => sprintf(
                "<a ses:no-track href=\"$unsubscribeUrl\">%s</a>",
                __('Unsubscribe')
            ),
            'unsubscribe_fallback' => sprintf(
                "<a ses:no-track href=\"$unsubscribeUrl\" style=\"color: white;\">%s</a>",
                __('Unsubscribe')
            ),
            'customer-shop' => Arr::get($additionalData, 'customer_shop'),
            'customer-email' => Arr::get($additionalData, 'customer_email'),
            'customer-url' => Arr::get($additionalData, 'customer_url'),
            'customer-register-date' => Arr::get($additionalData, 'customer_register_date'),
            'customer-address' => Arr::get($additionalData, 'customer_address'),

            'order-link' => Arr::get($additionalData, 'order_link'),
            'order-reference' => Arr::get($additionalData, 'order_reference'),
            'order-number' => Arr::get($additionalData, 'order_number'),
            'invoice-reference' => Arr::get($additionalData, 'invoice_reference'),
            'invoice-link' => Arr::get($additionalData, 'invoice_link'),
            'customer-link' => Arr::get($additionalData, 'customer_link'),
            'pallet-reference' => Arr::get($additionalData, 'pallet_reference'),
            'pallet-link' => Arr::get($additionalData, 'pallet_link'),
            'blade-new-order-transactions' => Arr::get($additionalData, 'blade_new_order_transactions'),
            'tracking' => Arr::get($additionalData, 'tracking'),
            'deletion-date' => Arr::get($additionalData, 'deletion_date'),
            'delivered-date' => Arr::get($additionalData, 'delivered_date'),
            'returned-date' => Arr::get($additionalData, 'returned_date'),
            'dispatched-date' => Arr::get($additionalData, 'dispatched_date'),
            'undispatched-date' => Arr::get($additionalData, 'undispatched_date'),
            'order-date' => Arr::get($additionalData, 'date'),
            'tracking-url' => Arr::get($additionalData, 'tracking_url'),
            'currency' => Arr::get($additionalData, 'currency'),
            'order-total' => Arr::get($additionalData, 'order_total'),
            'blade-order-total' => Arr::get($additionalData, 'blade_order_total'),
            'goods-amount' => Arr::get($additionalData, 'goods_amount'),
            'blade-goods-amount' => Arr::get($additionalData, 'blade_goods_amount'),
            'charges-amount' => Arr::get($additionalData, 'charges_amount'),
            'blade-charges-amount' => Arr::get($additionalData, 'blade_charges_amount'),
            'shipping-amount' => Arr::get($additionalData, 'shipping_amount'),
            'blade-shipping-amount' => Arr::get($additionalData, 'blade_shipping_amount'),
            'payment-amount' => Arr::get($additionalData, 'payment_amount'),
            'blade-payment-amount' => Arr::get($additionalData, 'blade_payment_amount'),
            'payment-type' => Arr::get($additionalData, 'payment_type'),
            'net-amount' => Arr::get($additionalData, 'net_amount'),
            'blade-net-amount' => Arr::get($additionalData, 'blade_net_amount'),
            'tax-amount' => Arr::get($additionalData, 'tax_amount'),
            'blade-tax-amount' => Arr::get($additionalData, 'blade_tax_amount'),
            'shop-name' => Arr::get($additionalData, 'shop_name'),
            'delivery-address' => Arr::get($additionalData, 'delivery_address'),
            'invoice-address' => Arr::get($additionalData, 'invoice_address'),
            'customer-note' => Arr::get($additionalData, 'customer_note'),
            'order' => Arr::get($additionalData, 'order'),
            'pay-info' => Arr::get($additionalData, 'pay_info'),
            'platform' => Arr::get($additionalData, 'platform'),
            'balance' => Arr::get($additionalData, 'balance'),
            'products' => Arr::get($additionalData, 'products'),
            'low-stock-items-in-basket' => Arr::get($additionalData, 'low_stock_items_in_basket'),
            'payment-reason' => Arr::get($additionalData, 'payment_reason'),
            'payment-note' => Arr::get($additionalData, 'payment_note'),
            'payment-balance-preview' => Arr::get($additionalData, 'payment_balance_preview'),
            'preview-amount' => Arr::get($additionalData, 'preview_amount'),
            'chat-link' => Arr::get($additionalData, 'chat_link'),
            'chat-message' => Arr::get($additionalData, 'chat_message'),
            'invoice-date-change-blade' => Arr::get($additionalData, 'invoice_date_change_blade'),
            'delivery-note-link' => Arr::get($additionalData, 'delivery_note_link'),
            'delivery-note-reference' => Arr::get($additionalData, 'delivery_note_reference'),
            'web-user-password' => Arr::get($additionalData, 'web_user_password'),
            'retina-login-link' => Arr::get($additionalData, 'retina_login_link'),
            'web-user-contact-name' => Arr::get($additionalData, 'web_user_contact_name'),

            default => $originalPlaceholder,
        };
    }

    public function getUsername(array $additionalData, int $dispatchedEmailId): string
    {
        if (Arr::get($additionalData, 'username')) {
            return Arr::get($additionalData, 'username');
        }

        if ($webUserDispatch = $this->getWebUserDispatch($dispatchedEmailId)) {
            return WebUser::find($webUserDispatch->web_user_id)?->username ?? '';
        }

        if ($userDispatch = $this->getUserDispatch($dispatchedEmailId)) {
            return User::find($userDispatch->user_id)?->username ?? '';
        }

        return '';
    }

    public function getName(WebUser|Customer|Prospect|User $recipient): string
    {
        if ($recipient instanceof WebUser) {
            return $recipient->customer->name;
        } elseif ($recipient instanceof Customer || $recipient instanceof Prospect) {
            return $recipient->name;
        } else {
            return $recipient->company_name ?? $recipient->username;
        }
    }

    private function getWebUserDispatch(int $dispatchedEmailId): ?object
    {
        return DB::table('web_user_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getUserDispatch(int $dispatchedEmailId): ?object
    {
        return DB::table('user_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getCustomerDispatch(int $dispatchedEmailId): ?object
    {
        return DB::table('customer_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getExternalSubscriberDispatch(int $dispatchedEmailId): ?object
    {
        return DB::table('external_subscriber_email_recipient_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getTestEmailRecipientDispatch(int $dispatchedEmailId): ?object
    {
        return DB::table('test_email_recipient_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getChatEmailRecipientDispatch(int $dispatchedEmailId): ?object
    {
        return DB::table('chat_email_recipient_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }
}
