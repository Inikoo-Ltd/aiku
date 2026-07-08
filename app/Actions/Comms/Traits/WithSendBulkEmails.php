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
        bool $debug = false,
        ?string $previewText = null,
        array $attachments = []
    ): DispatchedEmail {
        $html = $emailHtmlBody;
        $html = $this->processStyles($html);

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

        // 'u' flag ensures multibyte UTF-8 characters are not corrupted
        $html = preg_replace_callback('/{{(.*?)}}/u', $callback, $html);
        $html = preg_replace_callback('/\[(.*?)]/u', $callback, $html);

        // Restore MSO conditional comments
        if ($msoComments) {
            $html = str_replace(array_keys($msoComments), array_values($msoComments), $html);
        }

        // 'u' flag ensures multibyte UTF-8 characters are not corrupted
        $html = preg_replace('/\R+/u', '', $html);

        if ($previewText) {
            $html = $this->injectPreviewText($html, $previewText);
        }

        // Inject source parameter to links if encrypted_id exists
        // $html = $this->injectSourceParameterLinks($html, $dispatchedEmail);

        return SendSesEmail::run(
            subject: $subject,
            emailHtmlBody: $html,
            dispatchedEmail: $dispatchedEmail,
            sender: $sender,
            unsubscribeUrl: $unsubscribeUrl,
            senderName: $senderName,
            isTest: $isTest,
            debug: $debug,
            attachments: $attachments
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

            // TODO: Remove this logic to avoid sending email slow
            if ($webUserHasDispatchedEmail = $this->getWebUserDispatch($dispatchedEmail->id)) {
                $customerName = WebUser::on('aiku_no_sticky')->find($webUserHasDispatchedEmail->web_user_id)?->customer?->name ?? '';
            } elseif ($userHasDispatchedEmail = $this->getUserDispatch($dispatchedEmail->id)) {
                $customerName = Arr::get($additionalData, 'customer_name') ?? User::on('aiku_no_sticky')->find($userHasDispatchedEmail->user_id)?->contact_name ?? '';
            } elseif ($customerHasDispatchedEmail = $this->getCustomerDispatch($dispatchedEmail->id)) {
                $customerName = Customer::on('aiku_no_sticky')->find($customerHasDispatchedEmail->customer_id)?->name ?? '';
            } elseif ($externalSubscriberHasDispatchedEmail = $this->getExternalSubscriberDispatch($dispatchedEmail->id)) {
                $customerName = ExternalSubscriberEmailRecipient::on('aiku_no_sticky')->find($externalSubscriberHasDispatchedEmail->external_subscriber_email_recipient_id)?->name ?? '';
            } elseif ($testEmailRecipientHasDispatchedEmail = $this->getTestEmailRecipientDispatch($dispatchedEmail->id)) {
                $customerName = TestEmailRecipient::on('aiku_no_sticky')->find($testEmailRecipientHasDispatchedEmail->test_email_recipient_id)?->name ?? '';
            } elseif ($chatEmailRecipientHasDispatchedEmail = $this->getChatEmailRecipientDispatch($dispatchedEmail->id)) {
                $customerName = ChatEmailRecipient::on('aiku_no_sticky')->find($chatEmailRecipientHasDispatchedEmail->chat_email_recipient_id)?->name ?? '';
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
            'download-invoice-pdf' => Arr::get($additionalData, 'download_invoice_pdf_link'),
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

            'offer-code' => Arr::get($additionalData, 'offer_code'),
            'offer-name' => Arr::get($additionalData, 'offer_name'),
            'offer-link' => Arr::get($additionalData, 'offer_link'),
            'campaign-link' => Arr::get($additionalData, 'campaign_link'),
            'campaign-name' => Arr::get($additionalData, 'campaign_name'),
            'created-date' => Arr::get($additionalData, 'created_date'),
            'offer-state' => Arr::get($additionalData, 'offer_state'),
            'offer-type' => Arr::get($additionalData, 'offer_type'),
            'duration' => Arr::get($additionalData, 'duration'),
            'start-date' => Arr::get($additionalData, 'start_date'),
            'end-date' => Arr::get($additionalData, 'end_date'),
            'discount-type' => Arr::get($additionalData, 'discount_type'),
            'blade-discount-details' => Arr::get($additionalData, 'blade_discount_details'),
            'trigger-type' => Arr::get($additionalData, 'trigger_type'),
            'voucher' => Arr::get($additionalData, 'voucher'),
            'customer-id' => Arr::get($additionalData, 'customer_id'),


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

            'prospect-name' => Arr::get($additionalData, 'prospect_name'),
            'prospect-email' => Arr::get($additionalData, 'prospect_email'),
            'prospect-phone' => Arr::get($additionalData, 'prospect_phone'),
            'prospect-company-name' => Arr::get($additionalData, 'prospect_company_name'),

            'review-message' => Arr::get($additionalData, 'review_message'),
            'review-link' => Arr::get($additionalData, 'review_link'),
            'review-rating' => Arr::get($additionalData, 'rating_main'),
            'blade-review-images' => Arr::get($additionalData, 'blade_review_images'),

            'review-reminder-links' => Arr::get($additionalData, 'review_reminder_links'),

            default => $originalPlaceholder,
        };
    }

    public function getUsername(array $additionalData, int $dispatchedEmailId): string
    {
        if (Arr::get($additionalData, 'username')) {
            return Arr::get($additionalData, 'username');
        }

        if ($webUserDispatch = $this->getWebUserDispatch($dispatchedEmailId)) {
            return WebUser::on('aiku_no_sticky')->find($webUserDispatch->web_user_id)?->username ?? '';
        }

        if ($userDispatch = $this->getUserDispatch($dispatchedEmailId)) {
            return User::on('aiku_no_sticky')->find($userDispatch->user_id)?->username ?? '';
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
        return DB::connection('aiku_no_sticky')->table('web_user_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getUserDispatch(int $dispatchedEmailId): ?object
    {
        return DB::connection('aiku_no_sticky')->table('user_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getCustomerDispatch(int $dispatchedEmailId): ?object
    {
        return DB::connection('aiku_no_sticky')->table('customer_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getExternalSubscriberDispatch(int $dispatchedEmailId): ?object
    {
        return DB::connection('aiku_no_sticky')->table('external_subscriber_email_recipient_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getTestEmailRecipientDispatch(int $dispatchedEmailId): ?object
    {
        return DB::connection('aiku_no_sticky')->table('test_email_recipient_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    private function getChatEmailRecipientDispatch(int $dispatchedEmailId): ?object
    {
        return DB::connection('aiku_no_sticky')->table('chat_email_recipient_has_dispatched_emails')
            ->where('dispatched_email_id', $dispatchedEmailId)
            ->first();
    }

    // TODO: Re-enable this function when we need to inject source parameter to links
    // Purpose: to track which email campaign a user clicked on
    // Current issue: this function will be increase the email size significantly because we use encrypted_id in the link
    // Solution: find a way to minimize the email size or use a different approach
    private function injectSourceParameterLinks(string $html, DispatchedEmail $dispatchedEmail): string
    {
        // Check if encrypted_id exists in dispatchedEmail->data
        if (!isset($dispatchedEmail->data['encrypted_id'])) {
            return $html;
        }

        $encryptedId = $dispatchedEmail->data['encrypted_id'];

        // Remove MSO conditional comments temporarily to avoid processing them
        $msoComments = [];
        $html = preg_replace_callback('/<!--\[if mso\]>.*?<!\[endif\]-->/su', function ($matches) use (&$msoComments) {
            $placeholder = '___MSO_COMMENT_' . count($msoComments) . '___';
            $msoComments[$placeholder] = $matches[0];
            return $placeholder;
        }, $html);

        // Process all <a href="..."> tags
        $html = preg_replace_callback('/<a\s+([^>]*?)href\s*=\s*["\']([^"\']+)["\']([^>]*?)>/i', function ($matches) use ($encryptedId) {
            $beforeHref = $matches[1]; // attributes before href
            $url = $matches[2];        // the URL
            $afterHref = $matches[3];   // attributes after href

            // Skip if URL is empty or just a hash
            if (empty($url) || $url === '#') {
                return $matches[0];
            }

            // Skip if URL already has source parameter
            if (strpos($url, 'source=') !== false) {
                return $matches[0];
            }

            // Skip URLs containing '/unsubscribe/'
            if (strpos($url, '/unsubscribe/') !== false) {
                return $matches[0];
            }

            // Skip mailto:, tel:, and other non-http protocols
            if (preg_match('/^(mailto|tel|sms|ftp|javascript|data):/i', $url)) {
                return $matches[0];
            }

            // Add source parameter
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $newUrl = $url . $separator . 'source=' . urlencode($encryptedId);

            return '<a ' . $beforeHref . 'href="' . $newUrl . '"' . $afterHref . '>';
        }, $html);

        // Restore MSO conditional comments
        if ($msoComments) {
            $html = str_replace(array_keys($msoComments), array_values($msoComments), $html);
        }

        return $html;
    }

    private function injectPreviewText(string $htmlBody, string $previewText): string
    {
        $padding = str_repeat('&zwnj;&nbsp;', 140);

        $previewDiv = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">'
            . htmlspecialchars($previewText, ENT_QUOTES, 'UTF-8')
            . $padding
            . '</div>';

        // Inject right after <body> tag if present, otherwise prepend
        if (stripos($htmlBody, '<body') !== false) {
            return preg_replace('/(<body[^>]*>)/i', '$1' . $previewDiv, $htmlBody, 1);
        }

        return $previewDiv . $htmlBody;
    }
}
