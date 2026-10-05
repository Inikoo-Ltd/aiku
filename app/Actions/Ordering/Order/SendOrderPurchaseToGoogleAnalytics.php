<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 05 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Sends a submitted order's GA4 "purchase" through the Measurement Protocol, so it is counted
 * even when the customer's browser never pushes it: closed tab before GTM loaded, a session
 * flash lost to a concurrent request, or an order submitted by a payment webhook or sweeper.
 * Only orders whose checkout browser was captured by CaptureOrderGoogleAnalyticsClient are sent,
 * and only once (ga_purchase_sent_at), so a re-submitted order or a retried job is not counted twice.
 * Connection errors are rethrown without the request URL, which carries the API secret.
 */
class SendOrderPurchaseToGoogleAnalytics implements ShouldBeUnique
{
    use AsAction;

    private const string MEASUREMENT_PROTOCOL_URL = 'https://www.google-analytics.com/mp/collect';

    private const int MEASUREMENT_PROTOCOL_MAX_ITEMS = 200;

    public int $jobTries = 5;

    public int $jobBackoff = 60;

    public int $jobUniqueFor = 3600;

    public function getJobUniqueId(int $orderId): string
    {
        return (string)$orderId;
    }

    public function handle(int $orderId): void
    {
        $order = Order::find($orderId);

        if (!$order || !$order->submitted_at || $order->ga_purchase_sent_at || !$order->ga_client_id) {
            return;
        }

        $websiteSettings = $order->shop->website?->settings;
        $measurementId   = trim((string)Arr::get($websiteSettings, 'ga4_measurement_id'));
        $apiSecret       = trim((string)Arr::get($websiteSettings, 'ga4_api_secret'));

        if ($measurementId === '' || $apiSecret === '') {
            return;
        }

        $purchaseParams                   = GetOrderPurchaseEcommerceData::run($order);
        $purchaseParams['transaction_id'] = (string)$purchaseParams['transaction_id'];
        $purchaseParams['items']          = array_slice($purchaseParams['items'], 0, self::MEASUREMENT_PROTOCOL_MAX_ITEMS);

        if ($order->ga_session_id) {
            $purchaseParams['session_id']           = $order->ga_session_id;
            $purchaseParams['engagement_time_msec'] = 1;
        }

        try {
            $response = Http::timeout(10)->post(
                self::MEASUREMENT_PROTOCOL_URL.'?'.http_build_query([
                    'measurement_id' => $measurementId,
                    'api_secret'     => $apiSecret,
                ]),
                [
                    'client_id'        => $order->ga_client_id,
                    'timestamp_micros' => $order->submitted_at->getTimestamp() * 1_000_000,
                    'events'           => [
                        [
                            'name'   => 'purchase',
                            'params' => $purchaseParams,
                        ],
                    ],
                ]
            );
        } catch (ConnectionException) {
            throw new RuntimeException('GA4 Measurement Protocol unreachable for purchase of order '.$order->id);
        }

        if (!$response->successful()) {
            throw new RuntimeException('GA4 Measurement Protocol rejected purchase of order '.$order->id.': HTTP '.$response->status());
        }

        $order->updateQuietly([
            'ga_purchase_sent_at' => now(),
        ]);
    }
}
