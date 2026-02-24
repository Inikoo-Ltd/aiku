<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterShopTimeSeriesStats
{
    use AsObject;

    public function handle(Group $group, $from_date = null, $to_date = null): array
    {
        $masterShops = $group->masterShops()->get();

        $masterShops->load(['timeSeries' => function ($query) {
            $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        }]);

        $timeSeriesIds = [];
        $masterShopToTimeSeriesMap = [];

        foreach ($masterShops as $masterShop) {
            $dailyTimeSeries = $masterShop->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $masterShopToTimeSeriesMap[$masterShop->id] = $dailyTimeSeries->id;
            }
        }

        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
                    'sales_grp_currency'           => 'sales_grp_currency',
                    'lost_revenue_grp_currency'    => 'lost_revenue_grp_currency',
                    'baskets_created_grp_currency' => 'baskets_created_grp_currency',
                    'baskets_updated_grp_currency' => 'baskets_updated_grp_currency',
                    'invoices'                     => 'invoices',
                    'refunds'                      => 'refunds',
                    'orders'                       => 'orders',
                    'delivery_notes'               => 'delivery_notes',
                    'registrations_with_orders'    => 'registrations_with_orders',
                    'registrations_without_orders' => 'registrations_without_orders',
                    'customers_invoiced'           => 'customers_invoiced',
                ],
                'master_shop_time_series_records',
                'master_shop_time_series_id',
                $from_date,
                $to_date,
            );
        }

        $results = [];
        foreach ($masterShops as $masterShop) {
            $timeSeriesId = $masterShopToTimeSeriesMap[$masterShop->id] ?? null;
            $stats = $allStats[$timeSeriesId] ?? [];

            $intervals = ['1d', '1w', '1m', '1q', '1y', 'all', 'ytd', 'qtd', 'mtd', 'wtd', 'lm', 'lw', 'ld', 'lq', 'ly', 'tly', 'py', 'pq', 'pm', 'pw', 'ctm'];
            $registrationsData = [];

            foreach ($intervals as $interval) {
                $with = $stats["registrations_with_orders_{$interval}"] ?? 0;
                $without = $stats["registrations_without_orders_{$interval}"] ?? 0;
                $registrationsData["registrations_{$interval}"] = $with + $without;

                $withLy = $stats["registrations_with_orders_{$interval}_ly"] ?? 0;
                $withoutLy = $stats["registrations_without_orders_{$interval}_ly"] ?? 0;
                $registrationsData["registrations_{$interval}_ly"] = $withLy + $withoutLy;
            }

            $masterShopData = array_merge($masterShop->toArray(), $stats, $registrationsData, [
                'slug' => $masterShop->slug ?? 'unknown',
                'group_slug' => $masterShop->group->slug ?? 'unknown',
                'group_currency_code' => $masterShop->group->currency->code ?? 'GBP',
            ]);

            $results[] = $masterShopData;
        }

        return $results;
    }
}
