<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dashboard;

use App\Actions\Accounting\InvoiceCategory\GetInvoiceCategoryTimeSeriesStats;
use App\Actions\Catalogue\Shop\GetShopTimeSeriesStats;
use App\Actions\Dropshipping\Platform\GetPlatformTimeSeriesStats;
use App\Models\SysAdmin\Organisation;
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

        return Cache::remember($cacheKey, now()->addSeconds(300), function () use ($organisation, $fromDate, $toDate) {
            return $this->fetchData($organisation, $fromDate, $toDate);
        });
    }

    protected function getCacheKey(Organisation $organisation, $fromDate, $toDate): string
    {
        return sprintf(
            'dashboard:org_timeseries:%s:%s:%s',
            $organisation->id,
            $fromDate ?? 'null',
            $toDate ?? 'null'
        );
    }

    protected function fetchData(Organisation $organisation, $fromDate, $toDate): array
    {
        return [
            'shops' => GetShopTimeSeriesStats::run($organisation, $fromDate, $toDate),
            'invoiceCategories' => GetInvoiceCategoryTimeSeriesStats::run($organisation, $fromDate, $toDate),
            'platforms' => GetPlatformTimeSeriesStats::run($organisation, $fromDate, $toDate),
        ];
    }

    public static function clearCache(Organisation $organisation, $fromDate = null, $toDate = null): void
    {
        $instance = new static();
        $cacheKey = $instance->getCacheKey($organisation, $fromDate, $toDate);

        Cache::forget($cacheKey);
    }
}
