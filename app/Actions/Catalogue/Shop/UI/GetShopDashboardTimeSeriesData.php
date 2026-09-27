<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Dropshipping\Platform\GetPlatformTimeSeriesStats;
use App\Actions\Helpers\Brand\GetBrandTimeSeriesStats;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopDashboardTimeSeriesData
{
    use AsObject;

    public function handle(Shop $shop, $fromDate = null, $toDate = null, ?bool $useCache = null, bool $includePartners = false): array
    {
        $useCache = $useCache ?? true;

        if (!$useCache) {
            return $this->fetchData($shop, $fromDate, $toDate, $includePartners);
        }

        $cacheKey = $this->getCacheKey($shop, $fromDate, $toDate, $includePartners);

        return Cache::tags(["dashboard-shop-{$shop->id}"])
            ->remember($cacheKey, now()->addSeconds(300), function () use ($shop, $fromDate, $toDate, $includePartners) {
                return $this->fetchData($shop, $fromDate, $toDate, $includePartners);
            });
    }

    protected function getCacheKey(Shop $shop, $fromDate, $toDate, bool $includePartners): string
    {
        return sprintf(
            'dashboard:shop_data:%s:%s:%s%s',
            $shop->id,
            $fromDate ?? 'null',
            $toDate ?? 'null',
            $includePartners ? ':partners' : ''
        );
    }

    protected function fetchData(Shop $shop, $fromDate, $toDate, bool $includePartners): array
    {
        $data = [
            'shops'        => GetFormatedShopTimeSeriesStats::run($shop, $fromDate, $toDate, $includePartners),
            'brands'       => GetBrandTimeSeriesStats::run($shop, $fromDate, $toDate, $includePartners),
        ];

        if ($shop->type->value === 'dropshipping') {
            $data['platforms'] = GetPlatformTimeSeriesStats::run($shop, $fromDate, $toDate, $includePartners);
        }

        return $data;
    }

    public static function clearCache(Shop $shop): void
    {
        Cache::tags(["dashboard-shop-{$shop->id}"])->flush();
    }
}
