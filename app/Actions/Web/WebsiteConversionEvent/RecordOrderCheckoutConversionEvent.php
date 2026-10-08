<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteConversionEvent;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\Ordering\Order;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

class RecordOrderCheckoutConversionEvent
{
    use AsAction;

    public function handle(Order $order, Request $request): void
    {
        try {
            $this->record($order, $request);
        } catch (Throwable $e) {
            Sentry::captureException($e);
        }
    }

    private function record(Order $order, Request $request): void
    {
        if ($order->state != OrderStateEnum::CREATING || !$request->hasSession()) {
            return;
        }

        $website = $order->shop->website;

        if (!$website) {
            return;
        }

        StoreWebsiteConversionEvent::dispatch(
            sessionId: $request->session()->getId(),
            websiteId: $website->id,
            eventType: WebsiteConversionEventTypeEnum::CHECKOUT,
            url: $request->fullUrl(),
            orderId: $order->id
        )->delay(now()->addSeconds(10));
    }
}
