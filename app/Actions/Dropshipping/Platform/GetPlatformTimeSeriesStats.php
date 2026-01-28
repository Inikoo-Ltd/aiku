<?php

namespace App\Actions\Dropshipping\Platform;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPlatformTimeSeriesStats
{
    use AsObject;

    public function handle(Group|Organisation|Shop $parent, $from_date = null, $to_date = null): array
    {
        $platforms = [];

        if ($parent instanceof Group) {
            $platforms = Platform::where('group_id', $parent->id)->get();
        } else {
            $platforms = Platform::where('group_id', $parent->group_id)->get();
        }

        $platforms->load(['timeSeries' => function ($query) {
            $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        }]);

        $timeSeriesIds = [];
        $platformToTimeSeriesMap = [];

        foreach ($platforms as $platform) {
            $dailyTimeSeries = $platform->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $platformToTimeSeriesMap[$platform->id] = $dailyTimeSeries->id;
            }
        }

        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $additionalWhere = [];

            if ($parent instanceof Organisation) {
                $additionalWhere['organisation_id'] = $parent->id;
            } elseif ($parent instanceof Shop) {
                $additionalWhere['shop_id'] = $parent->id;
            }

            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
                    'sales'              => 'sales',
                    'sales_org_currency' => 'sales_org_currency',
                    'sales_grp_currency' => 'sales_grp_currency',
                    'invoices'           => 'invoices',
                    'channels'           => 'channels',
                    'customers'          => 'customers',
                    'portfolios'         => 'portfolios',
                    'customer_clients'   => 'customer_clients'
                ],
                'platform_time_series_records',
                'platform_time_series_id',
                $from_date,
                $to_date,
                $additionalWhere
            );
        }

        $totalSalesByInterval = $this->calculateTotalSales($allStats, $parent);

        $results = [];
        foreach ($platforms as $platform) {
            $timeSeriesId = $platformToTimeSeriesMap[$platform->id] ?? null;
            $stats = $allStats[$timeSeriesId] ?? [];

            if (empty($stats) || collect($stats)->every(fn ($value) => $value == 0)) {
                continue;
            }

            $statsWithPercentage = $this->addSalesPercentage($stats, $totalSalesByInterval, $parent);

            $platformData = array_merge($platform->toArray(), $statsWithPercentage);

            if ($parent instanceof Shop) {
                $platformData['shop_id'] = $parent->id;
                $platformData['shop_slug'] = $parent->slug;
                $platformData['organisation_slug'] = $parent->organisation->slug ?? null;
            } elseif ($parent instanceof Organisation) {
                $platformData['organisation_slug'] = $parent->slug;
            }

            $results[] = $platformData;
        }

        return $results;
    }

    /**
     * Calculate total sales for each interval based on parent type
     */
    private function calculateTotalSales(array $allStats, Group|Organisation|Shop $parent): array
    {
        $totals = [];
        $intervals = DateIntervalEnum::cases();

        $salesField = match (true) {
            // $parent instanceof Shop => 'sales',
            $parent instanceof Shop => 'sales_grp_currency',
            // $parent instanceof Organisation => 'sales_org_currency',
            $parent instanceof Organisation => 'sales_grp_currency',
            $parent instanceof Group => 'sales_grp_currency',
        };

        foreach ($intervals as $interval) {
            $key = $salesField . '_' . $interval->value;
            $totals[$key] = 0;

            foreach ($allStats as $stats) {
                $totals[$key] += (float)($stats[$key] ?? 0);
            }
        }

        return $totals;
    }

    /**
     * Add sales_percentage to stats for each interval
     */
    private function addSalesPercentage(array $stats, array $totalSalesByInterval, Group|Organisation|Shop $parent): array
    {
        $intervals = DateIntervalEnum::cases();

        $salesField = match (true) {
            // $parent instanceof Shop => 'sales',
            $parent instanceof Shop => 'sales_grp_currency',
            // $parent instanceof Organisation => 'sales_org_currency',
            $parent instanceof Organisation => 'sales_grp_currency',
            $parent instanceof Group => 'sales_grp_currency',
        };

        foreach ($intervals as $interval) {
            $key = $salesField . '_' . $interval->value;
            $totalSales = $totalSalesByInterval[$key] ?? 0;
            $platformSales = (float)($stats[$key] ?? 0);

            if ($totalSales > 0) {
                $percentage = ($platformSales / $totalSales) * 100;
            } else {
                $percentage = 0;
            }

            $stats['sales_percentage_' . $interval->value] = round($percentage, 2);
        }

        return $stats;
    }
}
