<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dashboard;

use App\Actions\Accounting\InvoiceCategory\GetInvoiceCategoryTimeSeriesStats;
use App\Actions\Ordering\Order\GetOrderBacklog;
use App\Actions\Catalogue\Shop\GetShopTimeSeriesStats;
use App\Actions\Dropshipping\Platform\GetPlatformTimeSeriesStats;
use App\Actions\Helpers\Brand\GetBrandTimeSeriesStats;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrganisationDashboardTimeSeriesData
{
    use AsObject;

    public function handle(Organisation $organisation, $fromDate = null, $toDate = null, ?bool $useCache = null, bool $includePartners = false): array
    {
        $useCache = $useCache ?? true;

        if (!$useCache) {
            return $this->fetchData($organisation, $fromDate, $toDate, $includePartners);
        }

        $cacheKey = $this->getCacheKey($organisation, $fromDate, $toDate, $includePartners);

        return Cache::tags(["dashboard-org-{$organisation->id}"])
            ->remember($cacheKey, now()->addSeconds(300), function () use ($organisation, $fromDate, $toDate, $includePartners) {
                return $this->fetchData($organisation, $fromDate, $toDate, $includePartners);
            });
    }

    protected function getCacheKey(Organisation $organisation, $fromDate, $toDate, bool $includePartners): string
    {
        [$normalizedFromDate, $normalizedToDate] = $this->normalizeDateBounds($fromDate, $toDate);

        return sprintf(
            'dashboard:org_timeseries:%s:%s:%s%s',
            $organisation->id,
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

    protected function fetchData(Organisation $organisation, $fromDate, $toDate, bool $includePartners): array
    {
        $backlog = GetOrderBacklog::run($organisation, $includePartners);

        return [
            'shops'             => GetOrderBacklog::addTo(GetShopTimeSeriesStats::run($organisation, $fromDate, $toDate, null, $includePartners), $backlog['shops']),
            'invoiceCategories' => GetOrderBacklog::addTo(GetInvoiceCategoryTimeSeriesStats::run($organisation, $fromDate, $toDate, $includePartners), $backlog['invoiceCategories']),
            'platforms'         => GetOrderBacklog::addTo(GetPlatformTimeSeriesStats::run($organisation, $fromDate, $toDate, $includePartners), $backlog['platforms']),
            'brands'            => GetOrderBacklog::addTo(GetBrandTimeSeriesStats::run($organisation, $fromDate, $toDate, $includePartners), $backlog['brands']),
        ];
    }

    public static function clearCache(Organisation $organisation): void
    {
        Cache::tags(["dashboard-org-{$organisation->id}"])->flush();
    }
}
