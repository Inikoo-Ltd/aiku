<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\UI\Dashboards;

use App\Actions\Accounting\InvoiceCategory\GetInvoiceCategoryTimeSeriesStats;
use App\Actions\Catalogue\Shop\GetShopTimeSeriesStats;
use App\Actions\Dropshipping\Platform\GetPlatformTimeSeriesStats;
use App\Actions\Ordering\SalesChannel\GetSalesChannelTimeSeriesStats;
use App\Actions\SysAdmin\Organisation\GetOrganisationTimeSeriesStats;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetGroupDashboardTimeSeriesData
{
    use AsObject;

    public function handle(Group $group, $fromDate = null, $toDate = null, ?bool $useCache = null): array
    {
        $useCache = $useCache ?? true;

        if (!$useCache) {
            return $this->fetchData($group, $fromDate, $toDate);
        }

        $cacheKey = $this->getCacheKey($group, $fromDate, $toDate);

        return Cache::remember($cacheKey, now()->addSeconds(300), function () use ($group, $fromDate, $toDate) {
            return $this->fetchData($group, $fromDate, $toDate);
        });
    }

    protected function getCacheKey(Group $group, $fromDate, $toDate): string
    {
        return sprintf(
            'dashboard:group_timeseries:%s:%s:%s',
            $group->id,
            $fromDate ?? 'null',
            $toDate ?? 'null'
        );
    }

    protected function fetchData(Group $group, $fromDate, $toDate): array
    {
        $allShops = GetShopTimeSeriesStats::run($group, $fromDate, $toDate);

        $shopsByType = [
            'all' => $allShops,
            'dropshipping' => [],
            'fulfilment' => [],
        ];

        foreach ($allShops as $shop) {
            $shopType = $shop['type'] ?? null;

            if ($shopType === ShopTypeEnum::DROPSHIPPING) {
                $shopsByType['dropshipping'][] = $shop;
            } elseif ($shopType === ShopTypeEnum::FULFILMENT) {
                $shopsByType['fulfilment'][] = $shop;
            }
        }

        return [
            'organisations' => GetOrganisationTimeSeriesStats::run($group, $fromDate, $toDate),
            'shops' => $shopsByType,
            'invoiceCategories' => GetInvoiceCategoryTimeSeriesStats::run($group, $fromDate, $toDate),
            'platforms' => GetPlatformTimeSeriesStats::run($group, $fromDate, $toDate),
            'salesChannels' => GetSalesChannelTimeSeriesStats::run($group, $fromDate, $toDate),
        ];
    }

    public static function clearCache(Group $group, $fromDate = null, $toDate = null): void
    {
        $instance = new static();
        $cacheKey = $instance->getCacheKey($group, $fromDate, $toDate);

        Cache::forget($cacheKey);
    }
}
