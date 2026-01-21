<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopTimeSeriesStats
{
    use AsObject;

    public function handle(Shop $shop, $from_date = null, $to_date = null): array
    {
        $shop->load([
            'timeSeries' => function ($query) {
                $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
            },
            'website.timeSeries' => function ($query) {
                $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
            },
        ]);

        $dailyTimeSeries = $shop->timeSeries->first();
        $websiteDailyTimeSeries = $shop->website?->timeSeries->first();

        if (!$dailyTimeSeries) {
            return [];
        }

        $metricsMapping = [
            'sales'                        => 'sales',
            'sales_org_currency'           => 'sales_org_currency',
            'sales_grp_currency'           => 'sales_grp_currency',
            'lost_revenue'                 => 'lost_revenue',
            'lost_revenue_org_currency'    => 'lost_revenue_org_currency',
            'lost_revenue_grp_currency'    => 'lost_revenue_grp_currency',
            'baskets_created'              => 'baskets_created',
            'baskets_created_org_currency' => 'baskets_created_org_currency',
            'baskets_created_grp_currency' => 'baskets_created_grp_currency',
            'baskets_updated'              => 'baskets_updated',
            'baskets_updated_org_currency' => 'baskets_updated_org_currency',
            'baskets_updated_grp_currency' => 'baskets_updated_grp_currency',
            'invoices'                     => 'invoices',
            'refunds'                      => 'refunds',
            'orders'                       => 'orders',
            'delivery_notes'               => 'delivery_notes',
            'registrations_with_orders'    => 'registrations_with_orders',
            'registrations_without_orders' => 'registrations_without_orders',
            'customers_invoiced'           => 'customers_invoiced',
        ];

        $rawStats = CalculateTimeSeriesStats::run(
            [$dailyTimeSeries->id],
            $metricsMapping,
            'shop_time_series_records',
            'shop_time_series_id',
            $from_date,
            $to_date
        );

        if ($websiteDailyTimeSeries) {
            $visitorStats = CalculateTimeSeriesStats::run(
                [$websiteDailyTimeSeries->id],
                ['visitors' => 'visitors'],
                'website_time_series_records',
                'website_time_series_id',
                $from_date,
                $to_date
            );
            if (!empty($visitorStats)) {
                if (empty($rawStats)) {
                    $rawStats[$dailyTimeSeries->id] = [];
                }
                $rawStats[$dailyTimeSeries->id] = array_merge($rawStats[$dailyTimeSeries->id], $visitorStats[$websiteDailyTimeSeries->id]);
            }
        }

        if (empty($rawStats)) {
            return [];
        }

        $metricsMapping['visitors'] = 'visitors';


        return (new CalculateTimeSeriesStats())->format(
            $rawStats[$dailyTimeSeries->id],
            $metricsMapping,
            $shop->currency->code
        );
    }
}
