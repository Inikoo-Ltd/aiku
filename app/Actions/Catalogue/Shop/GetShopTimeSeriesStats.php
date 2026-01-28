<?php

namespace App\Actions\Catalogue\Shop;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShopTimeSeriesStats
{
    use AsObject;

    public function handle(Group|Organisation $parent, $from_date = null, $to_date = null): array
    {
        $shops = $parent->shops()->get();

        $shops->load(['timeSeries' => function ($query) {
            $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        }]);

        $timeSeriesIds = [];
        $shopToTimeSeriesMap = [];

        foreach ($shops as $shop) {
            $dailyTimeSeries = $shop->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $shopToTimeSeriesMap[$shop->id] = $dailyTimeSeries->id;
            }
        }

        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
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
                ],
                'shop_time_series_records',
                'shop_time_series_id',
                $from_date,
                $to_date,
            );
        }

        $results = [];
        foreach ($shops as $shop) {
            $timeSeriesId = $shopToTimeSeriesMap[$shop->id] ?? null;
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

            $shopData = array_merge($shop->toArray(), $stats, $registrationsData, [
                'slug' => $shop->slug ?? 'Unknown',
                'organisation_slug' => $shop->organisation->slug ?? 'Unknown',
                'group_slug' => $shop->group->slug ?? 'Unknown',
                'shop_currency_code' => $shop->currency->code ?? 'GBP',
                'organisation_currency_code' => $shop->organisation->currency->code ?? 'GBP',
                'group_currency_code' => $shop->group->currency->code ?? 'GBP',
            ]);

            $results[] = $shopData;
        }

        return $results;
    }
}
