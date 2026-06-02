<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dashboard;

use App\Actions\Accounting\InvoiceCategory\GetInvoiceCategoryTimeSeriesStats;
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

    public function handle(Organisation $organisation, $fromDate = null, $toDate = null, ?bool $useCache = null): array
    {
        $useCache = $useCache ?? true;

        if (!$useCache) {
            return $this->fetchData($organisation, $fromDate, $toDate);
        }

        $cacheKey = $this->getCacheKey($organisation, $fromDate, $toDate);

        return Cache::tags(["dashboard-org-{$organisation->id}"])
            ->remember($cacheKey, now()->addSeconds(300), function () use ($organisation, $fromDate, $toDate) {
                return $this->fetchData($organisation, $fromDate, $toDate);
            });
    }

    protected function getCacheKey(Organisation $organisation, $fromDate, $toDate): string
    {
        [$normalizedFromDate, $normalizedToDate] = $this->normalizeDateBounds($fromDate, $toDate);

        return sprintf(
            'dashboard:org_timeseries:%s:%s:%s',
            $organisation->id,
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

    protected function fetchData(Organisation $organisation, $fromDate, $toDate): array
    {
        return [
            'shops'             => GetShopTimeSeriesStats::run($organisation, $fromDate, $toDate),
            'invoiceCategories' => GetInvoiceCategoryTimeSeriesStats::run($organisation, $fromDate, $toDate),
            'platforms'         => GetPlatformTimeSeriesStats::run($organisation, $fromDate, $toDate),
            'brands'            => GetBrandTimeSeriesStats::run($organisation, $fromDate, $toDate),
        ];
    }

    public static function clearCache(Organisation $organisation): void
    {
        Cache::tags(["dashboard-org-{$organisation->id}"])->flush();
    }
}
