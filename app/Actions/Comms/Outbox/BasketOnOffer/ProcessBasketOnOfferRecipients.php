<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Mon, 21 Sept 2026, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\BasketOnOffer;

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
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessBasketOnOfferRecipients
{
    use AsAction;

    public string $jobQueue = 'ses';

    protected const MAX_ITEMS = 5;

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

        foreach ($customers as $customer) {
            $customerModel = Customer::find($customer['id']);
            if (!$customerModel) {
                continue;
            }

            $dispatchedEmail = StoreDispatchedEmail::run(
                $emailBulkRun,
                $customerModel,
                [
                    'outbox_id'             => $outbox->id,
                    'email_address'         => $customerModel->email,
                    'data->additional_data' => [
                        'basket_on_offer_items' => $this->generateBasketOnOfferContent($customer['product_ids']),
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
        }

        app()->setLocale($previousLocale);

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

    public function generateBasketOnOfferContent(string $productIds): string
    {
        $ids = array_slice(array_unique(array_filter(explode(',', $productIds))), 0, self::MAX_ITEMS);

        if (empty($ids)) {
            return '';
        }

        $html = '<table width="100%" cellpadding="8" cellspacing="0"
        style="font-family: Helvetica, Arial, sans-serif;
               font-size: 14px;
               border-collapse: collapse;">';

        $html .= '
        <tr style="border-bottom:1px solid #e5e7eb;">
            <th align="left" style="color:#555;">'.__('Product').'</th>
            <th align="center" style="color:#555;">'.__('Offer').'</th>
            <th align="center" style="color:#555;">'.__('Price').'</th>
        </tr>';

        foreach ($ids as $productId) {
            $product = Product::find($productId);

            if (!$product || !$product->webpage) {
                continue;
            }

            $currencySymbol = $product->currency?->symbol ?? '$';
            $productImage   = Arr::get($product->imageSources(200, 200), 'png');
            $url            = $product->webpage->getCanonicalUrl();

            $percentageOff = Arr::get($product->offers_data, 'best_percentage_off.percentage_off');
            $offerLabel    = $percentageOff > 0
                ? percentage($percentageOff, 1).' '.__('off')
                : __('Price drop');

            $html .= '
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="vertical-align:middle;">
                        <table cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding-right:12px;">';

            if ($productImage) {
                $html .= '
                    <img src="'.$productImage.'"
                         width="60"
                         height="60"
                         style="display:block;
                                border-radius:6px;
                                object-fit:cover;" />';
            }

            $html .= '
                                </td>
                                <td style="vertical-align:middle;">
                                    <a ses:no-track href="'.$url.'"
                                       style="color:#2563eb;
                                        text-decoration:underline;
                                        font-weight:600;">'
                .e($product->name).
                '</a>
                                </td>
                            </tr>
                        </table>
                    </td>

                    <td align="center"
                        style="font-weight:600;
                               color:#dc2626;">'
                .$offerLabel.
                '</td>

                    <td align="center"
                        style="font-weight:600;
                               color:#16a34a;">'
                .$currencySymbol.' '.number_format($product->price ?? 0, 2).
                '</td>
                </tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
