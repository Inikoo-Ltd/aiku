<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-16h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithOrderingCustomerNotification;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

class SendNewOrderEmailToCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithOrderingCustomerNotification;

    private Email $email;

    public function handle(int $orderID): ?DispatchedEmail
    {

        $order = Order::find($orderID);
        if (!$order) {
            return null;
        }

        if ($order->shop->type === ShopTypeEnum::EXTERNAL) {
            return null;
        }

        list($emailHtmlBody, $dispatchedEmail) = $this->getEmailBody(
            $order->customer,
            OutboxCodeEnum::ORDER_CONFIRMATION
        );
        if (!$emailHtmlBody) {
            return null;
        }
        $outbox = $dispatchedEmail->outbox;

        $order->dispatchedEmails()->attach($dispatchedEmail, ['outbox_id' => $outbox->id]);

        return $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $outbox->emailOngoingRun?->email?->subject,
            $emailHtmlBody,
            '',
            additionalData: [
                'customer_name' => $order->customer->name,
                'order' => $this->generateOrderDetailsHtml($order),
                'pay_info' => $this->generateOrderPaymentsHtml($order),
                'date' => $order->submitted_at->format('F jS, Y'),
                'order_link' => $this->getOrderLink($order),
                'delivery_address' => $order->deliveryAddress->getHtml(),
                'invoice_address' => $order->billingAddress->getHtml(),
                'customer_note' => $order->customer_notes,
                'order_number' => $this->getOrderLinkHtml($order),
                'order_reference' => $this->getOrderLinkHtml($order),
            ],
            senderName: $outbox->emailOngoingRun->senderName(),
        );
    }

    public string $commandSignature = 'test:send-new_order-email';


    public function asCommand(): void
    {
        $order = Order::where('slug', 'awd151455')->first();

        $this->handle($order->id);
    }

    public function generateOrderDetailsHtml(Order $order): string
    {
        $html = '';

        // Product Line Items Section with padding wrapper
        $html .= '<div style="padding: 18px;">';
        $html .= '<table width="100%" cellpadding="8" cellspacing="0" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; border-collapse: collapse;">';


        $html .= '<tr style="border-bottom: 1px solid #e9e9e9;">
            <td style="width:60px;"></td>
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: left; font-weight: bold; color: #777;">' . __('Product') . '</td>
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: center; font-weight: bold; color: #777;">' . __('Qty') . '</td>
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: right; font-weight: bold; color: #777;">' . __('Amount') . '</td>
        </tr>';


        $productTransactions = $order->transactions()
            ->where('model_type', 'Product')
            ->get();
        $currency            = $order->shop->currency->symbol;
        foreach ($productTransactions as $transaction) {

            $product = $transaction->model;
            $productImage = Arr::get($product?->imageSources(200, 200), 'original', '');
            $productLink = $product?->webpage?->getCanonicalUrl();

            $productName = $transaction->historicAsset->name ?? 'N/A';

            if ($productLink) {
                $productName = sprintf(
                    '<a href="%s" target="_blank"
                style="color:#3498DB;
                       text-decoration:underline;
                       font-weight:500;">%s</a>',
                    $productLink,
                    $productName
                );
            }

            // Handle offer data and price display
            $offerData = $transaction?->offers_data;
            $priceDisplay = '';
            $quantity = $transaction->quantity_ordered ?? 1;

            if ($offerData && isset($offerData['o'])) {
                // With discount - show strikethrough original price
                $originalPrice = ($product->price ?? 0) * $quantity;
                $priceDisplay = sprintf(
                    '<div style="text-align: right;">
                        <span style="text-decoration: line-through; margin-right: 4px; color: #666; font-size: 12px;">%s%s</span>
                        %s%s
                    </div>',
                    $currency,
                    number_format($originalPrice, 2),
                    $currency,
                    number_format($transaction->net_amount, 2)
                );
            } else {
                // Without discount - normal display
                $priceDisplay = $currency . number_format($transaction->net_amount, 2);
            }

            // Generate discount label if offer data exists
            $discountLabel = '';
            if ($offerData && isset($offerData['o'])) {
                $percentage = $offerData['o']['p'] ?? '';
                $label = $offerData['o']['l'] ?? '';

                // Clean percentage format (same logic as formatPercentage in Vue)
                $cleanPercentage = preg_replace('/\.0%$/', '%', $percentage);

                $discountLabel = sprintf(
                    '<br/>
                    <div style="
                        background-color: rgba(34, 197, 94, 0.1);
                        padding: 2px 4px;
                        font-size: 11px;
                        border: 1px solid rgba(34, 197, 94, 0.3);
                        border-radius: 3px;
                        display: inline-block;
                        color: #15803d;
                        margin-top: 4px;
                        font-weight: bold;
                        max-width: 300px; 
                        white-space: normal; 
                        word-break: break-word;
                    ">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="display: inline-block; margin-right: 2px; vertical-align: -2px;">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M14.665 3.04046C13.147 1.68411 10.8526 1.68411 9.3346 3.04046L9.09292 3.25641C8.75513 3.55824 8.32487 3.73646 7.87259 3.76189L7.54899 3.78008C5.51656 3.89435 3.8941 5.5168 3.77983 7.54924L3.76164 7.87283C3.73621 8.32512 3.558 8.75537 3.25616 9.09316L3.04021 9.33485C1.68386 10.8528 1.68386 13.1473 3.04021 14.6652L3.25616 14.9069C3.558 15.2447 3.73621 15.675 3.76164 16.1273L3.77983 16.4508C3.8941 18.4833 5.51656 20.1057 7.54899 20.22L7.87259 20.2382C8.32487 20.2636 8.75513 20.4418 9.09292 20.7437L9.3346 20.9596C10.8526 22.316 13.147 22.316 14.665 20.9596L14.9067 20.7437C15.2445 20.4418 15.6747 20.2636 16.127 20.2382L16.4506 20.22C18.483 20.1057 20.1055 18.4833 20.2198 16.4509L20.238 16.1273C20.2634 15.675 20.4416 15.2447 20.7434 14.9069L20.9594 14.6652C22.3157 13.1473 22.3157 10.8528 20.9594 9.33484L20.7434 9.09316C20.4416 8.75537 20.2634 8.32512 20.238 7.87283L20.2198 7.54924C20.1055 5.5168 18.483 3.89435 16.4506 3.78008L16.127 3.76189C15.6747 3.73646 15.2445 3.55824 14.9067 3.25641L14.665 3.04046ZM15.7071 8.29289C16.0976 8.68342 16.0976 9.31658 15.7071 9.70711L9.70711 15.7071C9.31658 16.0976 8.68342 16.0976 8.29289 15.7071C7.90237 15.3166 7.90237 14.6834 8.29289 14.2929L14.2929 8.29289C14.6834 7.90237 15.3166 7.90237 15.7071 8.29289ZM16 14.5C16 15.3284 15.3284 16 14.5 16C13.6716 16 13 15.3284 13 14.5C13 13.6716 13.6716 13 14.5 13C15.3284 13 16 13.6716 16 14.5ZM9.5 11C10.3284 11 11 10.3284 11 9.5C11 8.67157 10.3284 8 9.5 8C8.67157 8 8 8.67157 8 9.5C8 10.3284 8.67157 11 9.5 11Z"/>
                        </svg>
                        <span style="margin-left: 1px; vertical-align: middle;">%s %s</span>
                    </div>',
                    $cleanPercentage,
                    $label
                );
            }

            $html .= sprintf(
                '<tr style="border-bottom: 1px solid #e9e9e9;">
                    <td style="padding:12px 8px; vertical-align:middle;">
                        %s
                    </td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: left; color: #666;">
                        <strong style="color: #555;">%s</strong><br/>
                        <span style="color: #888;">%s</span>
                        %s
                    </td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: center; color: #666;">%s</td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 12px 8px; text-align: right; color: #666;">%s</td>
                </tr>',
                $productImage
                    ? '<img src="' . $productImage . '"
                   width="48"
                   height="48"
                   style="display:block;
                          border-radius:6px;
                          object-fit:cover;" />'
                    : '',
                $transaction->historicAsset->code ?? 'N/A',
                $productName,
                $discountLabel,
                rtrim(number_format($transaction->quantity_ordered, 3), '0.'),
                $priceDisplay
            );
        }

        $html .= '</table>';
        $html .= '</div>'; // Close padding wrapper

        // Totals Section with padding wrapper
        $html .= '<div style="padding: 0 22px 22px 22px;">';
        $html .= '<table width="100%" cellpadding="8" cellspacing="0" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 20px 0 0 0;">';

        // Calculate savings
        $savings = ($order->gross_amount ?? 0) - ($order->goods_amount ?? 0);

        // Items row - show both original (strikethrough) and discounted prices
        if ($savings > 0) {
            // With discount - show both prices
            $html .= sprintf(
                '<tr>
                    <td style="width: 70%%; padding: 8px;"></td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">' . __('Items') . '</td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">
                        <span style="text-decoration: line-through; color: #666; margin-right: 4px; font-size: 12px;">%s%s</span>
                        %s%s
                    </td>
                </tr>',
                $currency,
                number_format($order->gross_amount ?? 0, 2),
                $currency,
                number_format($order->goods_amount ?? 0, 2)
            );
        } else {
            // No discount - show normal price
            $html .= sprintf(
                '<tr>
                    <td style="width: 70%%; padding: 8px;"></td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">' . __('Items') . '</td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
                </tr>',
                $currency,
                number_format($order->goods_amount ?? 0, 2)
            );
        }

        // Add Total Savings row if there are savings
        if ($savings > 0) {
            $html .= sprintf(
                '<tr>
                    <td style="width: 70%%; padding: 8px;"></td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #15803d;">' . __('Total Savings') . '</td>
                    <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #15803d;">%s%s</td>
                </tr>',
                $currency,
                number_format($savings, 2)
            );
        }

        $html .= sprintf(
            '<tr>
                <td style="width: 70%%; padding: 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">' . __('Charges') . '</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->charges_amount ?? 0, 2)
        );

        $html .= sprintf(
            '<tr>
                <td style="width: 70%%; padding: 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">' . __('Shipping') . '</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->shipping_amount ?? 0, 2)
        );

        $html .= sprintf(
            '<tr>
                <td style="width: 70%%; padding: 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">' . __('Total net') . '</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->net_amount ?? 0, 2)
        );

        $html .= sprintf(
            '<tr>
                <td style="width: 70%%; padding: 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #777;">' . __('Tax') . '</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px; text-align: right; border-bottom: 1px solid #e9e9e9; color: #666;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->tax_amount ?? 0, 2)
        );

        $html .= sprintf(
            '<tr style="background-color: #f9f9f9;">
                <td style="width: 70%%; padding: 12px 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">' . __('Total') . '</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->total_amount ?? 0, 2)
        );

        $html .= sprintf(
            '<tr style="background-color: #f9f9f9;">
                <td style="width: 70%%; padding: 12px 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">' . __('Paid') . '</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">%s%s</td>
            </tr>',
            $currency,
            number_format($order->payment_amount ?? 0, 2)
        );

        $html .= sprintf(
            '<tr style="background-color: #f9f9f9;">
                <td style="width: 70%%; padding: 12px 8px;"></td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; padding: 12px 8px; text-align: right; border-bottom: 2px solid #333; color: #555;">' . __('To Pay Amount') . '</td>
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


        if ($shopType == ShopTypeEnum::DROPSHIPPING) {
            $baseUrl = 'https://ds.test';
            if (app()->isProduction()) {
                $baseUrl = 'https://' . $shop->website->domain;
            }

            $orderUrl = $baseUrl . '/app/dropshipping/channels/' . $order->customerSalesChannel->slug . '/orders/' . $order->slug;

            return sprintf(
                '<a href="%s" target="_blank" style="color: #3498DB; text-decoration: underline; font-weight: 500;">%s</a>',
                $orderUrl,
                $order->reference ?? $order->slug ?? __('View Order')
            );
        }
        // TODO important do this for B2B

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

        $html     = '';
        $currency = $order->shop->currency->symbol;

        $html .= '<div style="margin-top: 30px;">
            <h3 style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 16px; font-weight: bold; color: #555; margin: 0 0 15px 0;">' . __('Payments') . '</h3>
        </div>';

        $html .= '<table width="100%" cellpadding="8" cellspacing="0" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; border-collapse: collapse;">';

        $html .= '<tr style="border-bottom: 1px solid #e9e9e9;">
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: left; font-weight: bold; color: #777;">' . __('Reference') . '</td>
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: center; font-weight: bold; color: #777;">' . __('Method') . '</td>
            <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: right; font-weight: bold; color: #777;">' . __('Amount') . '</td>
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
}
