<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox\AbandonedCart;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRunRecipient;
use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\Ordering\CheckoutAbandonment;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessAbandonedCartRecipients
{
    use AsAction;

    public string $jobQueue = 'ses';

    public function handle(?int $emailBulkRunId, array $customers): void
    {
        if (!$emailBulkRunId) {
            return;
        }

        $emailBulkRun = EmailBulkRun::find($emailBulkRunId);

        if (!$emailBulkRun) {
            return;
        }

        $outbox = Outbox::find($emailBulkRun->outbox_id);

        if (!$outbox) {
            return;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($outbox->shop->language->code);

        $emailDeliveryChannel = StoreEmailDeliveryChannel::run($emailBulkRun, [
            'state' => EmailDeliveryChannelStateEnum::IN_PROCESS->value,
        ]);

        $checkoutUrl = $this->getCheckoutUrl($outbox);
        $sentAbandonmentIds = [];

        foreach ($customers as $customer) {
            $customerModel = Customer::find($customer['id']);
            if (!$customerModel) {
                continue;
            }

            $dispatchedEmail = StoreDispatchedEmail::run(
                $emailBulkRun,
                $customerModel,
                [
                    'outbox_id'     => $outbox->id,
                    'email_address' => $customerModel->email,
                    'data->additional_data' => [
                        'checkout_url'         => $checkoutUrl,
                        'abandoned_cart_items' => $this->generateRecoveryContent($customer['order_id'], $checkoutUrl),
                    ]
                ]
            );

            StoreEmailBulkRunRecipient::run(
                $emailBulkRun,
                [
                    'dispatched_email_id' => $dispatchedEmail->id,
                    'recipient_type'      => class_basename($customerModel),
                    'recipient_id'        => $customerModel->id,
                    'channel'             => $emailDeliveryChannel->id,
                    'recipient_name'      => $customerModel->name,
                ]
            );

            $sentAbandonmentIds[] = $customer['abandonment_id'];
        }

        app()->setLocale($previousLocale);

        if (!empty($sentAbandonmentIds)) {
            CheckoutAbandonment::whereIn('id', $sentAbandonmentIds)->update(['email_sent_at' => now()]);
        }

        UpdateEmailDeliveryChannel::run(
            $emailDeliveryChannel,
            [
                'number_emails' => $emailBulkRun->recipients()->where('channel', $emailDeliveryChannel->id)->count(),
                'state'         => EmailDeliveryChannelStateEnum::READY->value
            ]
        );
        UpdateEmailBulkRunRecipientStoredAt::run($emailBulkRun);

        SendEmailDeliveryChannel::dispatch($emailDeliveryChannel->id)->delay(5);
    }

    public function getCheckoutUrl(Outbox $outbox): string
    {
        $website = $outbox->shop->website;

        if (!$website) {
            return '';
        }

        return $website->getFullUrl() . '/checkout';
    }

    public function generateRecoveryContent(int $orderId, string $checkoutUrl): string
    {
        $order = Order::find($orderId);

        if (!$order) {
            return '';
        }

        $productIds = $order->itemTransactions()
            ->pluck('model_id')
            ->all();

        $displayProducts = array_slice($productIds, 0, 5);
        $remainingCount = count($productIds) - count($displayProducts);

        $html = '';

        if ($checkoutUrl) {
            $html .= '<p style="text-align:center; margin:0 0 20px;">
                <a ses:no-track href="' . $checkoutUrl . '"
                   style="display:inline-block;
                          background:#2563eb;
                          color:#ffffff;
                          font-family: Helvetica, Arial, sans-serif;
                          font-size:15px;
                          font-weight:600;
                          text-decoration:none;
                          padding:12px 24px;
                          border-radius:6px;">'
                . __('Complete your order') .
                '</a>
            </p>';
        }

        $html .= '<table width="100%" cellpadding="8" cellspacing="0"
        style="font-family: Helvetica, Arial, sans-serif;
               font-size: 14px;
               border-collapse: collapse;">';

        foreach ($displayProducts as $productId) {
            $dataProduct = Product::find($productId);

            if (!$dataProduct || !$dataProduct->webpage) {
                continue;
            }

            $productImage = Arr::get($dataProduct->imageSources(200, 200), 'png');
            $url = $dataProduct->webpage->getCanonicalUrl();
            $name = $dataProduct->name;

            $html .= '
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="vertical-align:middle;">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="padding-right:12px;">';

            if ($productImage) {
                $html .= '
                <img src="' . $productImage . '"
                     width="60"
                     height="60"
                     style="display:block;
                            border-radius:6px;
                            object-fit:cover;" />';
            }

            $html .= '
                            </td>
                            <td style="vertical-align:middle;">
                                <a ses:no-track href="' . $url . '"
                                   style="color:#2563eb;
                                    text-decoration:underline;
                                    font-weight:600;">'
                . $name .
                '</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>';
        }

        $html .= '</table>';

        if ($remainingCount > 0) {
            $html .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #555; margin-top: 12px;">';
            $html .= __('and ') . $remainingCount . ($remainingCount > 1 ? __(' mores') : __(' more'));
            $html .= '</p>';
        }

        return $html;
    }
}
