<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Catalogue\ProductCategory\GetDepartmentTimeSeriesStats;
use App\Actions\Catalogue\ProductCategory\GetSubDepartmentTimeSeriesStats;
use App\Actions\Dropshipping\Platform\GetPlatformTimeSeriesStats;
use App\Actions\Helpers\Brand\GetBrandTimeSeriesStats;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopDashboardTimeSeriesData
{
    use AsObject;

    /**
     * Each table is built and cached on its own, so a page only pays for the tables it shows.
     *
     * @param  array<int, string>  $keys  shops, brands, departments, sub_departments, platforms
     */
    public function handle(Shop $shop, array $keys, $fromDate = null, $toDate = null, ?bool $useCache = null, bool $includePartners = false): array
    {
        $useCache = $useCache ?? true;
        $cacheKey = $this->getCacheKey($shop, $fromDate, $toDate, $includePartners);

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [
                $key => $useCache
                    ? Cache::tags(["dashboard-shop-{$shop->id}"])->remember("$cacheKey:$key", now()->addSeconds(300), fn () => $this->fetchData($key, $shop, $fromDate, $toDate, $includePartners))
                    : $this->fetchData($key, $shop, $fromDate, $toDate, $includePartners),
            ])
            ->all();
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

    protected function fetchData(string $key, Shop $shop, $fromDate, $toDate, bool $includePartners): array
    {
        return match ($key) {
            'shops'           => GetFormatedShopTimeSeriesStats::run($shop, $fromDate, $toDate, $includePartners),
            'brands'          => GetBrandTimeSeriesStats::run($shop, $fromDate, $toDate, $includePartners),
            'departments'     => GetDepartmentTimeSeriesStats::run($shop, $fromDate, $toDate),
            'sub_departments' => GetSubDepartmentTimeSeriesStats::run($shop, $fromDate, $toDate),
            'platforms'       => $shop->type->value === 'dropshipping' ? GetPlatformTimeSeriesStats::run($shop, $fromDate, $toDate, $includePartners) : [],
        };
    }

    public static function clearCache(Shop $shop): void
    {
        Cache::tags(["dashboard-shop-{$shop->id}"])->flush();
    }
}
