<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteConversionEvent;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Models\Web\WebsiteConversionEvent;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebsiteConversionEvent
{
    use AsAction;
    use InteractsWithQueue;

    public string $jobQueue = 'analytics';

    public function handle(
        string $sessionId,
        int $websiteId,
        WebsiteConversionEventTypeEnum|string $eventType,
        string $url,
        ?int $productId = null,
        int $quantity = 1
    ): void {
        $visitor = WebsiteVisitor::query()
            ->where('session_id', $sessionId)
            ->where('website_id', $websiteId)
            ->first();

        // Visitor not processed yet (Race condition), retry in 5 seconds
        if (!$visitor) {
            $this->release(5);

            return;
        }

        $website = Website::find($websiteId);

        if (!$website) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        $webpage = $this->resolveWebpage($website, $path);

        WebsiteConversionEvent::create([
            'group_id'           => $visitor->group_id,
            'organisation_id'    => $visitor->organisation_id,
            'website_visitor_id' => $visitor->id,
            'webpage_id'         => $webpage?->id,
            'website_id'         => $website->id,
            'shop_id'            => $website->shop_id,
            'event_type'         => $eventType,
            'product_id'         => $productId,
            'quantity'           => $quantity,
            'page_url'           => $url,
            'page_path'          => $path,
            'event_date'         => now()->toDateString(),
        ]);
    }

    protected function resolveWebpage(Website $website, string $path): ?Webpage
    {
        $cacheKey = "webpage_resolve:{$website->id}:" . md5($path);

        return Cache::remember($cacheKey, 86400, function () use ($website, $path) {
            return Webpage::query()
                ->where('website_id', $website->id)
                ->where('state', WebpageStateEnum::LIVE)
                ->where(function ($query) use ($path) {
                    $query->whereRaw("substring(canonical_url from 'https?://[^/]+(/.*)$') = ?", [$path])
                        ->orWhere('url', ltrim($path, '/'));
                })
                ->first();
        });
    }
}
