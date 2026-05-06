<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\UI\Dashboards;

use App\Actions\Accounting\InvoiceCategory\GetInvoiceCategoryTimeSeriesStats;
use App\Actions\Catalogue\Shop\GetShopTimeSeriesStats;
use App\Actions\Dropshipping\Platform\GetPlatformTimeSeriesStats;
use App\Actions\Helpers\Brand\GetBrandTimeSeriesStats;
use App\Actions\Ordering\SalesChannel\GetSalesChannelTimeSeriesStats;
use App\Actions\SysAdmin\Organisation\GetOrganisationTimeSeriesStats;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\SysAdmin\Group;
use Carbon\Carbon;
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

        return Cache::tags(["dashboard-group-{$group->id}"])
            ->remember($cacheKey, now()->addSeconds(300), function () use ($group, $fromDate, $toDate) {
                return $this->fetchData($group, $fromDate, $toDate);
            });
    }

    protected function getCacheKey(Group $group, $fromDate, $toDate): string
    {
        [$normalizedFromDate, $normalizedToDate] = $this->normalizeDateBounds($fromDate, $toDate);

        return sprintf(
            'dashboard:group_timeseries:%s:%s:%s',
            $group->id,
            $normalizedFromDate,
            $normalizedToDate
        );
    }

    protected function normalizeDateBounds($fromDate, $toDate): array
    {
        if (empty($fromDate) && empty($toDate)) {
            return ['all', 'all'];
        }

        return [
            $this->normalizeDateToken($fromDate),
            $this->normalizeDateToken($toDate),
        ];
    }

    protected function normalizeDateToken($date): string
    {
        if (empty($date)) {
            return 'open';
        }

        if ($date instanceof Carbon) {
            return $date->toDateString();
        }

        return Carbon::parse((string) $date)->toDateString();
    }

    protected function fetchData(Group $group, $fromDate, $toDate): array
    {
        $allShops = GetShopTimeSeriesStats::run($group, $fromDate, $toDate);
        $allInvoiceCategories = GetInvoiceCategoryTimeSeriesStats::run($group, $fromDate, $toDate);

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

        $faireInvoiceCategories = collect($allInvoiceCategories)
            ->filter(fn ($category) => str_contains(strtolower($category['name'] ?? ''), 'faire'))
            ->map(function ($category) {
                $category['is_global_marketplaces'] = true;

                return $category;
            })
            ->values()
            ->all();

        return [
            'organisations' => GetOrganisationTimeSeriesStats::run($group, $fromDate, $toDate),
            'shops' => $shopsByType,
            'invoiceCategories' => $allInvoiceCategories,
            'faire' => $faireInvoiceCategories,
            'platforms' => GetPlatformTimeSeriesStats::run($group, $fromDate, $toDate),
            'salesChannels' => GetSalesChannelTimeSeriesStats::run($group, $fromDate, $toDate),
            'brands' => GetBrandTimeSeriesStats::run($group, $fromDate, $toDate),
        ];
    }

    public static function clearCache(Group $group): void
    {
        Cache::tags(["dashboard-group-{$group->id}"])->flush();
    }
}
