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

    public function handle(Order $order): ?DispatchedEmail
    {
        list($emailHtmlBody, $dispatchedEmail) = $this->getEmailBody(
            $order->customer,
            OutboxCodeEnum::ORDER_CONFIRMATION
        );
        if (!$emailHtmlBody) {
            return null;
        }
        $outbox = $dispatchedEmail->outbox;


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
                'date' => $order->created_at->format('F jS, Y'),
                'order_link' => $this->getOrderLink($order),
                'delivery_address' => $order->deliveryAddress->getHtml(),
                'invoice_address' => $order->billingAddress->getHtml(),
                'customer_note' => $order->customer_notes,
                'order_number' => $this->getOrderLinkHtml($order),
            ]
        );
    }

    public string $commandSignature = 'test:send-new_order-email';


    public function asCommand(): void
    {
        $order = Order::where('slug', 'awd151455')->first();

        $this->handle($order);
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
        $currency            = $order->shop->currency->symbol;
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
        // TODO important do this bor B2B

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

}
