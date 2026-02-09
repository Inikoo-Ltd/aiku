<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Dropshipping\Platform\GetPlatformTimeSeriesStats;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopDashboardTimeSeriesData
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
            'dashboard:shop_data:%s:%s:%s',
            $shop->id,
            $fromDate ?? 'null',
            $toDate ?? 'null'
        );
    }

    protected function fetchData(Shop $shop, $fromDate, $toDate): array
    {
        $data = [
            'shops' => GetFormatedShopTimeSeriesStats::run($shop, $fromDate, $toDate),
        ];

        if ($shop->type->value === 'dropshipping') {
            $data['platforms'] = GetPlatformTimeSeriesStats::run($shop, $fromDate, $toDate);
        }

        return $data;
    }

    public static function clearCache(Shop $shop, $fromDate = null, $toDate = null): void
    {
        $instance = new static();
        $cacheKey = $instance->getCacheKey($shop, $fromDate, $toDate);

        Cache::forget($cacheKey);
    }
}
