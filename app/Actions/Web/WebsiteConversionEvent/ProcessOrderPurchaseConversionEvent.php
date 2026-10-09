<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteConversionEvent;

use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\Ordering\Order;
use App\Models\Web\WebsiteConversionEvent;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessOrderPurchaseConversionEvent implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public int $jobUniqueFor = 3600;

    public function getJobUniqueId(int $orderId): string
    {
        return (string) $orderId;
    }

    public function handle(int $orderId, ?string $sessionId = null, ?string $url = null): void
    {
        $order = Order::find($orderId);

        if (!$order || !$order->submitted_at) {
            return;
        }

        $website = $order->shop->website;

        if (!$website) {
            return;
        }

        $alreadyRecorded = WebsiteConversionEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', WebsiteConversionEventTypeEnum::PURCHASE)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $checkoutEvent = WebsiteConversionEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', WebsiteConversionEventTypeEnum::CHECKOUT)
            ->latest('id')
            ->first();

        $visitor = $sessionId
            ? WebsiteVisitor::query()
                ->where('session_id', $sessionId)
                ->where('website_id', $website->id)
                ->first()
            : null;

        $visitor ??= $checkoutEvent?->websiteVisitor;

        if (!$visitor) {
            return;
        }

        $pageUrl = $url ?? $checkoutEvent?->page_url ?? $website->getUrl();

        WebsiteConversionEvent::firstOrCreate(
            [
                'order_id'   => $order->id,
                'event_type' => WebsiteConversionEventTypeEnum::PURCHASE,
            ],
            [
                'group_id'           => $visitor->group_id,
                'organisation_id'    => $visitor->organisation_id,
                'website_visitor_id' => $visitor->id,
                'webpage_id'         => null,
                'website_id'         => $website->id,
                'shop_id'            => $website->shop_id,
                'product_id'         => null,
                'quantity'           => 1,
                'net_amount'         => $order->net_amount,
                'landing_webpage_id' => $checkoutEvent?->landing_webpage_id ?? GetVisitorLandingWebpage::run($visitor, $order->submitted_at),
                'page_url'           => mb_substr($pageUrl, 0, 4096),
                'page_path'          => mb_substr(parse_url($pageUrl, PHP_URL_PATH) ?: '/', 0, 2048),
                'event_date'         => $order->submitted_at->toDateString(),
            ]
        );
    }
}
