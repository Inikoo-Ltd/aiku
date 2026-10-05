<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 05 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * Remembers on the order the GA4 browser that is checking it out, so its purchase can be sent
 * from the server (SendOrderPurchaseToGoogleAnalytics) whichever way the order ends up submitted:
 * the customer's own request, a payment webhook or a sweeper, with no browser to push it.
 * Only a browser where GA4 already set its cookie is recorded: no cookie means GA4 is blocked or
 * analytics consent was not given, and then nothing is sent for that order. Analytics must never
 * stop a checkout, so any failure here is reported and swallowed.
 */
class CaptureOrderGoogleAnalyticsClient
{
    use AsAction;

    public function handle(Order $order, Request $request): void
    {
        try {
            $this->captureClient($order, $request);
        } catch (Throwable $e) {
            Sentry::captureException($e);
        }
    }

    private function captureClient(Order $order, Request $request): void
    {
        if ($order->state != OrderStateEnum::CREATING) {
            return;
        }

        $websiteSettings = $order->shop->website?->settings;
        $measurementId   = trim((string)Arr::get($websiteSettings, 'ga4_measurement_id'));
        $apiSecret       = trim((string)Arr::get($websiteSettings, 'ga4_api_secret'));

        if ($measurementId === '' || $apiSecret === '') {
            return;
        }

        $clientId = $this->parseClientId($request->cookie('_ga'));

        if (!$clientId) {
            return;
        }

        $sessionId = $this->parseSessionId($request->cookie('_ga_'.preg_replace('/^G-/', '', $measurementId)));

        if ($order->ga_client_id === $clientId && $order->ga_session_id === $sessionId) {
            return;
        }

        $order->updateQuietly([
            'ga_client_id'  => $clientId,
            'ga_session_id' => $sessionId,
        ]);
    }

    /**
     * "GA1.1.1234567890.1700000000" → "1234567890.1700000000"
     */
    public function parseClientId(mixed $gaCookie): ?string
    {
        $cookieParts = explode('.', is_string($gaCookie) ? $gaCookie : '');

        if (count($cookieParts) < 4 || !ctype_digit($cookieParts[2]) || !ctype_digit($cookieParts[3])) {
            return null;
        }

        return $cookieParts[2].'.'.$cookieParts[3];
    }

    /**
     * "GS1.1.1700000000.5.1.1700000100.0.0.0" or "GS2.1.s1700000000$o5$g1$t1700000100$j0$l0$h0" → "1700000000"
     */
    public function parseSessionId(mixed $gaSessionCookie): ?string
    {
        $sessionPart = explode('.', is_string($gaSessionCookie) ? $gaSessionCookie : '')[2] ?? '';

        if (str_starts_with($sessionPart, 's')) {
            $sessionPart = substr(explode('$', $sessionPart)[0], 1);
        }

        return ctype_digit($sessionPart) ? $sessionPart : null;
    }
}
