<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dashboard;

use App\Actions\Accounting\InvoiceCategory\GetInvoiceCategoryTimeSeriesStats;
use App\Actions\Catalogue\Shop\GetShopTimeSeriesStats;
use App\Actions\CRM\Customer\GetTopCustomersStats;
use App\Actions\Dropshipping\Platform\GetPlatformTimeSeriesStats;
use App\Actions\Helpers\Brand\GetBrandTimeSeriesStats;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrganisationDashboardTimeSeriesData
{
    use AsObject;

    public function handle(Organisation $organisation, $fromDate = null, $toDate = null, ?bool $useCache = null, int $topCustomersLimit = 10): array
    {
        $useCache = $useCache ?? true;

        if (!$useCache) {
            return $this->fetchData($organisation, $fromDate, $toDate, $topCustomersLimit);
        }

        $cacheKey = $this->getCacheKey($organisation, $fromDate, $toDate, $topCustomersLimit);

        return Cache::tags(["dashboard-org-{$organisation->id}"])
            ->remember($cacheKey, now()->addSeconds(300), function () use ($organisation, $fromDate, $toDate, $topCustomersLimit) {
                return $this->fetchData($organisation, $fromDate, $toDate, $topCustomersLimit);
            });
    }

    protected function getCacheKey(Organisation $organisation, $fromDate, $toDate, int $topCustomersLimit): string
    {
        [$normalizedFromDate, $normalizedToDate] = $this->normalizeDateBounds($fromDate, $toDate);

        return sprintf(
            'dashboard:org_timeseries:%s:%s:%s:top%d',
            $organisation->id,
            $normalizedFromDate,
            $normalizedToDate,
            $topCustomersLimit
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

    protected function fetchData(Organisation $organisation, $fromDate, $toDate, int $topCustomersLimit = 10): array
    {
        return [
            'shops'             => GetShopTimeSeriesStats::run($organisation, $fromDate, $toDate),
            'invoiceCategories' => GetInvoiceCategoryTimeSeriesStats::run($organisation, $fromDate, $toDate),
            'platforms'         => GetPlatformTimeSeriesStats::run($organisation, $fromDate, $toDate),
            'brands'            => GetBrandTimeSeriesStats::run($organisation, $fromDate, $toDate),
            'topCustomers'      => GetTopCustomersStats::run($organisation, $fromDate, $toDate, $topCustomersLimit),
        ];
    }

    public static function clearCache(Organisation $organisation): void
    {
        Cache::tags(["dashboard-org-{$organisation->id}"])->flush();
    }
}
