<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\UI;

use App\Actions\Discounts\OfferCampaign\UI\GetOfferCampaignsTimeSeriesStats;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDiscountsDashboardTimeSeriesData
{
    use AsObject;

    public function handle(Shop $shop, $fromDate = null, $toDate = null, ?bool $useCache = null): array
    {
        $useCache = $useCache ?? true;

        if (!$useCache) {
            return $this->fetchData($shop, $fromDate, $toDate);
        }

        $cacheKey = $this->getCacheKey($shop, $fromDate, $toDate);

        return Cache::remember($cacheKey, now()->addSeconds(300), function () use ($shop, $fromDate, $toDate) {
            return $this->fetchData($shop, $fromDate, $toDate);
        });
    }

    protected function getCacheKey(Shop $shop, $fromDate, $toDate): string
    {
        return sprintf(
            'dashboard:discounts:%s:%s:%s',
            $shop->id,
            $fromDate ?? 'null',
            $toDate ?? 'null'
        );
    }

    protected function fetchData(Shop $shop, $fromDate, $toDate): array
    {
        return [
            'offerCampaigns' => GetOfferCampaignsTimeSeriesStats::run($shop, $fromDate, $toDate),
        ];
    }

    public static function clearCache(Shop $shop, $fromDate = null, $toDate = null): void
    {
        $instance = new static();
        $cacheKey = $instance->getCacheKey($shop, $fromDate, $toDate);

        Cache::forget($cacheKey);
    }
}
