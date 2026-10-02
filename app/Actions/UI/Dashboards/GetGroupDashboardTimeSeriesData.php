<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\UI\Dashboards;

use App\Actions\Accounting\InvoiceCategory\GetInvoiceCategoryTimeSeriesStats;
use App\Actions\Ordering\Order\GetOrderBacklog;
use App\Actions\Catalogue\Shop\GetShopTimeSeriesStats;
use App\Actions\Comms\Mailshot\GetShopMailshotsSentStats;
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

    public function handle(Group $group, $fromDate = null, $toDate = null, ?bool $useCache = null, bool $includePartners = false): array
    {
        $useCache = $useCache ?? true;

        if (!$useCache) {
            return $this->fetchData($group, $fromDate, $toDate, $includePartners);
        }

        $cacheKey = $this->getCacheKey($group, $fromDate, $toDate, $includePartners);

        return Cache::tags(["dashboard-group-{$group->id}"])
            ->remember($cacheKey, now()->addSeconds(300), function () use ($group, $fromDate, $toDate, $includePartners) {
                return $this->fetchData($group, $fromDate, $toDate, $includePartners);
            });
    }

    protected function getCacheKey(Group $group, $fromDate, $toDate, bool $includePartners): string
    {
        [$normalizedFromDate, $normalizedToDate] = $this->normalizeDateBounds($fromDate, $toDate);

        return sprintf(
            'dashboard:group_timeseries:%s:%s:%s%s',
            $group->id,
            $normalizedFromDate,
            $normalizedToDate,
            $includePartners ? ':partners' : ''
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

    protected function fetchData(Group $group, $fromDate, $toDate, bool $includePartners): array
    {
        $backlog = GetOrderBacklog::run($group, $includePartners);

        $allShops = GetOrderBacklog::addTo(GetShopTimeSeriesStats::run($group, $fromDate, $toDate, null, $includePartners), $backlog['shops']);
        $allInvoiceCategories = GetInvoiceCategoryTimeSeriesStats::run($group, $fromDate, $toDate, $includePartners, $backlog['invoiceCategories']);

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
            'organisations' => GetOrderBacklog::addTo(GetOrganisationTimeSeriesStats::run($group, $fromDate, $toDate, $includePartners), $backlog['organisations']),
            'shops' => $shopsByType,
            'invoiceCategories' => $allInvoiceCategories,
            'faire' => $faireInvoiceCategories,
            'platforms' => GetOrderBacklog::addTo(GetPlatformTimeSeriesStats::run($group, $fromDate, $toDate, $includePartners), $backlog['platforms']),
            'salesChannels' => GetOrderBacklog::addTo(GetSalesChannelTimeSeriesStats::run($group, $fromDate, $toDate, $includePartners), $backlog['salesChannels']),
            'brands' => GetOrderBacklog::addTo(GetBrandTimeSeriesStats::run($group, $fromDate, $toDate, $includePartners), $backlog['brands']),
            'mailshots' => GetShopMailshotsSentStats::run($group, $fromDate, $toDate),
        ];
    }

    public static function clearCache(Group $group): void
    {
        Cache::tags(["dashboard-group-{$group->id}"])->flush();
    }
}
