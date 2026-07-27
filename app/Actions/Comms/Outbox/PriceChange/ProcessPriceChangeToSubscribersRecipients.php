<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 25 Mar 2026 09:43:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\PriceChange;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRunRecipient;
use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\Comms\ExternalSubscriberEmailRecipient\StoreExternalSubscriberEmailRecipient;
use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\ExternalSubscriberEmailRecipient;
use App\Models\Comms\OutBoxHasSubscriber;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPriceChangeToSubscribersRecipients
{
    use AsAction;

    public string $jobQueue = 'ses';

    public function handle(?int $emailBulkRunId, array $subscriberIds, string $groupedProductIds): void
    {
        if (!$emailBulkRunId) {
            return;
        }

        $emailBulkRun = EmailBulkRun::find($emailBulkRunId);

        if (!$emailBulkRun) {
            return;
        }

        if (!$emailBulkRun->outbox_id) {
            return;
        }

        $emailDeliveryChannel = StoreEmailDeliveryChannel::run($emailBulkRun, [
            'state' => EmailDeliveryChannelStateEnum::IN_PROCESS->value,
        ]);

        $products = $this->generateMasterAssetBlocks($groupedProductIds);

        $subscribers = OutBoxHasSubscriber::whereIn('id', $subscriberIds)->with('user')->get();

        foreach ($subscribers as $subscriber) {
            $recipient = $this->resolveRecipient($emailBulkRun, $subscriber);

            if (!$recipient || !filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $dispatchedEmail = StoreDispatchedEmail::run(
                $emailBulkRun,
                $recipient,
                [
                    'outbox_id'     => $emailBulkRun->outbox_id,
                    'email_address' => $recipient->email,
                    'data->additional_data' => [
                        'products' => $products
                    ]
                ]
            );

            StoreEmailBulkRunRecipient::run(
                $emailBulkRun,
                [
                    'dispatched_email_id' => $dispatchedEmail->id,
                    'recipient_type'      => class_basename($recipient),
                    'recipient_id'        => $recipient->id,
                    'channel'             => $emailDeliveryChannel->id,
                    'recipient_name'      => $recipient instanceof User
                        ? $recipient->contact_name ?? $recipient->username
                        : "-",
                ]
            );
        }

        // After processing the chunk, update and dispatch the delivery channel
        UpdateEmailDeliveryChannel::run(
            $emailDeliveryChannel,
            [
                'number_emails' => $emailBulkRun->recipients()->where('channel', $emailDeliveryChannel->id)->count(),
                'state'         => EmailDeliveryChannelStateEnum::READY->value
            ]
        );
        UpdateEmailBulkRunRecipientStoredAt::run($emailBulkRun);

        SendEmailDeliveryChannel::dispatch($emailDeliveryChannel->id)->delay(2);
    }

    public function resolveRecipient(EmailBulkRun $emailBulkRun, OutBoxHasSubscriber $subscriber): User|ExternalSubscriberEmailRecipient|null
    {
        if ($subscriber->user) {
            return $subscriber->user;
        }

        if (!$subscriber->external_email) {
            return null;
        }

        $group = $emailBulkRun->group;

        return $group->externalSubscriberEmailRecipients()->where('email', $subscriber->external_email)->first()
            ?? StoreExternalSubscriberEmailRecipient::make()->action($group, [
                'name'  => 'Who appropriate',
                'email' => $subscriber->external_email,
            ]);
    }

    public function generateMasterAssetBlocks(string $groupedProductIds): string
    {
        try {
            $grouped = json_decode($groupedProductIds, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $th) {
            \Log::error('Error parsing grouped product IDs: ' . $th->getMessage());
            return '';
        }

        $masterAssets = MasterAsset::whereIn('id', array_keys($grouped))->get()->keyBy('id');

        $dataProducts = Product::whereIn('id', Arr::flatten($grouped))
            ->with(['currency', 'webpage'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $date = Carbon::now()->format('d M y');

        $html = '';

        foreach ($grouped as $masterAssetId => $productIds) {
            $masterAsset = $masterAssets->get($masterAssetId);

            if (!$masterAsset) {
                continue;
            }

            $html .= '
            <div style="margin-bottom:28px;">
                <div style="font-family: Helvetica, Arial, sans-serif;
                            font-size:15px;
                            font-weight:700;
                            color:#111827;
                            padding-bottom:4px;">'
                . $masterAsset->name .
                '</div>
                <div style="font-family: Helvetica, Arial, sans-serif;
                            font-size:13px;
                            color:#6b7280;
                            padding-bottom:10px;">'
                . __('Master asset updated on :date. The products below do not follow it and need rechecking.', ['date' => $date]) .
                '</div>';

            $html .= '<table width="100%" cellpadding="8" cellspacing="0"
            style="font-family: Helvetica, Arial, sans-serif;
                   font-size: 14px;
                   border-collapse: collapse;">';

            $html .= '
            <tr style="border-bottom:1px solid #e5e7eb;">
                <th align="left" style="color:#555;">' . __('Product') . '</th>
                <th align="center" style="color:#555;">' . __('Master Price') . '</th>
                <th align="center" style="color:#555;">' . __('Product Price') . '</th>
            </tr>';

            foreach ($productIds as $productId) {
                $dataProduct = $dataProducts->get($productId);

                if (!$dataProduct || !$dataProduct->webpage) {
                    continue;
                }

                $currencySymbol = $dataProduct->currency?->symbol ?? '$';

                $masterPrice = $dataProduct->currency
                    ? $masterAsset->getPriceFromCurrency($dataProduct->currency)
                    : $masterAsset->price;

                $productPrice = $dataProduct->price ?? 0;

                $productImage = Arr::get(
                    $dataProduct->imageSources(200, 200),
                    'png'
                );

                $url  = $dataProduct->webpage->getCanonicalUrl();
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

                    <td align="center"
                        style="font-weight:600;
                               color:#16a34a;">'
                    . $currencySymbol . ' ' . number_format((float) $masterPrice, 2) .
                    '</td>

                    <td align="center"
                        style="font-weight:600;
                               color:#dc2626;">'
                    . $currencySymbol . ' ' . number_format((float) $productPrice, 2) .
                    '</td>
                </tr>';
            }

            $html .= '</table></div>';
        }

        return $html;
    }
}
