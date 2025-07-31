<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Dec 2023 13:11:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\Ses\SendSesEmail;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\OutBoxHasSubscriber;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait WithSendBulkEmails
{
    public function sendEmailWithMergeTags(DispatchedEmail $dispatchedEmail, string $sender, string $subject, string $emailHtmlBody, string $unsubscribeUrl = null, string $passwordToken = null, string $invoiceUrl = null, array $additionalData = []): DispatchedEmail
    {
        $html = $emailHtmlBody;

        $html = $this->processStyles($html);
        if (preg_match_all("/{{(.*?)}}/", $html, $matches)) {
            foreach ($matches[1] as $i => $placeholder) {
                $placeholder = $this->replaceMergeTags($placeholder, $dispatchedEmail, $unsubscribeUrl, $passwordToken, $invoiceUrl, $additionalData);
                $html        = str_replace($matches[0][$i], sprintf('%s', $placeholder), $html);
            }
        }

        if (preg_match_all("/\[(.*?)]/", $html, $matches)) {
            foreach ($matches[1] as $i => $tag) {
                $placeholder = $this->replaceMergeTags($tag, $dispatchedEmail, $unsubscribeUrl, $passwordToken, $invoiceUrl, $additionalData);
                $html        = str_replace($matches[0][$i], sprintf('%s', $placeholder), $html);
                $xx[] = $placeholder;
            }
        }

        $html = preg_replace('/\R+/', '', $html);

        return SendSesEmail::run(
            subject: $subject,
            emailHtmlBody: $html,
            dispatchedEmail: $dispatchedEmail,
            sender: $sender,
            unsubscribeUrl: $unsubscribeUrl
        );
    }

    private function replaceMergeTags($placeholder, $dispatchedEmail, $unsubscribeUrl = null, $passwordToken = null, $invoiceUrl = null, array $additionalData = []): ?string
    {
        $originalPlaceholder = $placeholder;
        $placeholder         = Str::kebab(trim($placeholder));

        if ($dispatchedEmail->recipient instanceof WebUser) {
            $customerName = $dispatchedEmail->recipient->customer->name;
        } elseif ($dispatchedEmail->recipient instanceof OutBoxHasSubscriber || $dispatchedEmail->recipient instanceof User) {
            $customerName = Arr::get($additionalData, 'customer_name');
        } else {
            $customerName = $dispatchedEmail->recipient->name;
        }

        return match ($placeholder) {
            'username' => $this->getUsername($dispatchedEmail->recipient),
            'customer-name' => $customerName,
            'rejected-notes' => Arr::get($additionalData, 'rejected_notes'),
            'invoice_-url' => $invoiceUrl,
            'reset_-password_-u-r-l' => $passwordToken,
            'unsubscribe' => sprintf(
                "<a ses:no-track href=\"$unsubscribeUrl\">%s</a>",
                __('Unsubscribe')
            ),
            'customer-shop' => Arr::get($additionalData, 'customer_shop'),
            'customer-email' => Arr::get($additionalData, 'customer_email'),
            'customer-url' => Arr::get($additionalData, 'customer_url'),
            'customer-register-date' => Arr::get($additionalData, 'customer_register_date'),

            'order-link' => Arr::get($additionalData, 'order_link'),
            'order-reference' => Arr::get($additionalData, 'order_reference'),
            'order-number' => $this->getOrderLinkHtml(Arr::get($additionalData, 'order')),
            'invoice-reference' => Arr::get($additionalData, 'invoice_reference'),
            'invoice-link' => Arr::get($additionalData, 'invoice_link'),
            'customer-link' => Arr::get($additionalData, 'customer_link'),
            'pallet-reference' => Arr::get($additionalData, 'pallet_reference'),
            'pallet-link' => Arr::get($additionalData, 'pallet_link'),
            'order-transactions' => $this->generateOrderTransactionsHtml(Arr::get($additionalData, 'order_transactions')),
            'tracking' => Arr::get($additionalData, 'tracking'),
            'deletion-date',
            'delivered-date',
            'returned-date',
            'order-date' => Arr::get($additionalData, 'date'),
            'tracking-url' => Arr::get($additionalData, 'tracking_url'),
            'currency' => Arr::get($additionalData, 'currency'),
            'order-total' => Arr::get($additionalData, 'order_total'),
            'goods-amount' => Arr::get($additionalData, 'goods_amount'),
            'charges-amount' => Arr::get($additionalData, 'charges_amount'),
            'shipping-amount' => Arr::get($additionalData, 'shipping_amount'),
            'payment-amount' => Arr::get($additionalData, 'payment_amount'),
            'payment-type' => Arr::get($additionalData, 'payment_type'),
            'net-amount' => Arr::get($additionalData, 'net_amount'),
            'tax-amount' => Arr::get($additionalData, 'tax_amount'),
            'shop-name' => Arr::get($additionalData, 'shop_name'),
            'delivery-address' => Arr::get($additionalData, 'delivery_address'),
            'invoice-address' => Arr::get($additionalData, 'invoice_address'),
            'customer-note' => Arr::get($additionalData, 'customer_note'),
            'order' => $this->generateOrderDetailsHtml(Arr::get($additionalData, 'order')),
            'pay-info' => $this->generateOrderPaymentsHtml(Arr::get($additionalData, 'order')),
            default => $originalPlaceholder,
        };
    }

    public function getUsername(WebUser|Customer|Prospect|User $recipient): string
    {
        if ($recipient instanceof WebUser || $recipient instanceof User) {
            return $recipient->username;
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

    private function generateOrderTransactionsHtml($transactions): string
    {
        if (!$transactions) {
            return '';
        }
        
        if (is_string($transactions)) {
            $transactions = json_decode($transactions, true);
        }
        
        $html = '';
        foreach ($transactions as $transaction) {
            $historicAsset = $transaction->historicAsset;
            
            $html .= sprintf(
                '<tr style="border-bottom: 1px solid #e9e9e9;">
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: left;">
                        <strong>%s</strong><br/>
                        <span style="color: #666;">%s</span>
                    </td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: center;">%s</td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: right;">£%s</td>
                </tr>',
                $historicAsset->code ?? 'N/A',
                $historicAsset->name ?? 'N/A',
                $transaction->quantity_ordered ?? '0',
                $transaction->net_amount ?? '0'
            );
        }

        return $html;
    }

    public function generateOrderDetailsHtml(Order $order): string
    {
        $html = '';
        
        // Product Line Items Section with padding wrapper
        $html .= '<div style="padding: 18px;">';
        $html .= '<table width="100%" cellpadding="8" cellspacing="0" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; border-collapse: collapse;">';
        

        $html .= '<tr style="border-bottom: 1px solid #e9e9e9;">
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: left; font-weight: bold; color: #777;">Product</td>
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: center; font-weight: bold; color: #777;">Qty</td>
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: right; font-weight: bold; color: #777;">Amount</td>
        </tr>';
        

        $productTransactions = $order->transactions()
            ->where('model_type', 'Product')
            ->get();
        $currency = $order->shop->currency->symbol;
        foreach ($productTransactions as $transaction) {
            $html .= sprintf(
                '<tr style="border-bottom: 1px solid #e9e9e9;">
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: left; color: #666;">
                        <strong style="color: #555;">%s</strong><br/>
                        <span style="color: #888;">%s</span>
                    </td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: center; color: #666;">%s</td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: right; color: #666;">%s%s</td>
                </tr>',
                $transaction->historicAsset->code ?? 'N/A',
                $transaction->historicAsset->name ?? 'N/A',
                rtrim(number_format($transaction->quantity_ordered, 3), '0.'),
                $currency,
                number_format($transaction->net_amount, 2)
            );
        }
        
        $html .= '</table>';
        $html .= '</div>'; // Close padding wrapper
        
        // Totals Section with padding wrapper
        $html .= '<div style="padding: 0 22px 22px 22px;">';
        $html .= '<table width="100%" cellpadding="8" cellspacing="0" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 20px 0 0 0;">';
        
        $html .= sprintf(
            '<tr>
                <td style="width: 70%%; padding: 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">Items</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->goods_amount ?? 0, 2)
        );
        
        $html .= sprintf(
            '<tr>
                <td style="width: 70%%; padding: 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">Charges</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->charges_amount ?? 0, 2)
        );
        
        $html .= sprintf(
            '<tr>
                <td style="width: 70%%; padding: 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">Shipping</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->shipping_amount ?? 0, 2)
        );
        
        $html .= sprintf(
            '<tr>
                <td style="width: 70%%; padding: 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">Total net</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->net_amount ?? 0, 2)
        );
        
        $html .= sprintf(
            '<tr>
                <td style="width: 70%%; padding: 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">Tax</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->tax_amount ?? 0, 2)
        );
        
        $html .= sprintf(
            '<tr style="background-color: #f9f9f9;">
                <td style="width: 70%%; padding: 12px 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">Total</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->total_amount ?? 0, 2)
        );
        
        $html .= sprintf(
            '<tr style="background-color: #f9f9f9;">
                <td style="width: 70%%; padding: 12px 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">Paid</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->payment_amount ?? 0, 2)
        );
        
        $html .= sprintf(
            '<tr style="background-color: #f9f9f9;">
                <td style="width: 70%%; padding: 12px 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">To Pay Amount</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">%s%s</td>
            </tr>',
            $currency,
            number_format(($order->total_amount ?? 0) - ($order->payment_amount ?? 0), 2)
        );
        
        $html .= '</table>';
        $html .= '</div>'; // Close padding wrapper
        
        return $html;
    }

    public function getOrderLinkHtml(?Order $order): string
    {
        if (!$order) {
            return '';
        }

        $shop     = $order->shop;
        $shopType = $shop->type;
        $baseUrl  = '';
        $orderUrl = '';
        
        if ($shopType == ShopTypeEnum::DROPSHIPPING) {
            $baseUrl = 'https://ds.test';
            if (app()->isProduction()) {
                $baseUrl = 'https://'.$shop->website->domain;
            }

            $orderUrl = $baseUrl.'/app/dropshipping/channels/'.$order->customerSalesChannel->slug.'/orders/'.$order->slug;
            
            return sprintf(
                '<a href="%s" target="_blank" style="color: #3498DB; text-decoration: underline; font-weight: 500;">%s</a>',
                $orderUrl,
                $order->reference ?? $order->slug ?? 'View Order'
            );
        }

        // If not dropshipping or no URL generated, return just the order reference
        return sprintf(
            '<span style="color: #555; font-weight: 500;">%s</span>',
            $order->reference ?? $order->slug ?? 'N/A'
        );
    }

    public function generateOrderPaymentsHtml(Order $order): string
    {
        $payments = $order->payments;

        if (!$payments || $payments->isEmpty()) {
            return '';
        }

        $html = '';
        $currency = $order->shop->currency->symbol;
        
        $html .= '<div style="margin-top: 30px;">
            <h3 style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; color: #555; margin: 0 0 15px 0;">Payments</h3>
        </div>';
        
        $html .= '<table width="100%" cellpadding="8" cellspacing="0" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; border-collapse: collapse;">';
        
        $html .= '<tr style="border-bottom: 1px solid #e9e9e9;">
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: left; font-weight: bold; color: #777;">Reference</td>
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: center; font-weight: bold; color: #777;">Method</td>
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: right; font-weight: bold; color: #777;">Amount</td>
        </tr>';
        
        foreach ($payments as $payment) {
            $html .= sprintf(
                '<tr style="border-bottom: 1px solid #e9e9e9;">
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: left; color: #666;">
                        <strong style="color: #555;">%s</strong>
                    </td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: center; color: #666;">
                        <span style="color: #888;">%s</span>
                    </td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: right; color: #666;">%s%s</td>
                </tr>',
                $payment->reference ?? 'N/A',
                $payment->paymentAccount->name ?? 'N/A',
                $currency,
                number_format($payment->amount ?? 0, 2)
            );
        }
        
        $html .= '</table>';

        return $html;
    }


    public function processStyles($html): array|string|null
    {
        $html = preg_replace_callback('/<[^>]+style=["\'](.*?)["\'][^>]*>/i', function ($match) {
            $style = $match[1];

            // Find and modify color values within the style attribute
            $style = preg_replace_callback('/color\s*:\s*([^;]+);/i', function ($colorMatch) {
                $colorValue    = $colorMatch[1];
                $modifiedColor = $colorValue.' !important';

                return 'color: '.$modifiedColor.';';
            }, $style);

            // Update the style attribute in the HTML tag
            return str_replace($match[1], $style, $match[0]);
        }, $html);

        // Remove <style> tags and their content
        return preg_replace('/<style(.*?)>(.*?)<\/style>/is', '', $html);
    }
}
