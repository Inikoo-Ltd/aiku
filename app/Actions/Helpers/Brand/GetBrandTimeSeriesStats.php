<?php

namespace App\Actions\Helpers\Brand;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Helpers\Brand;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsObject;

class GetBrandTimeSeriesStats
{
    use AsObject;

    public function handle(Group $group, $from_date = null, $to_date = null): array
    {
        $brands = Brand::query()
            ->select(['id', 'slug', 'name', 'group_id'])
            ->where('group_id', $group->id)
            ->whereHas('timeSeries', function ($query) {
                $query->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
            })
            ->with([
                'timeSeries' => function ($query) {
                    $query->select(['id', 'brand_id', 'frequency'])
                        ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value);
                },
            ])
            ->get();

        $timeSeriesIds = [];
        $brandToTimeSeriesMap = [];

        foreach ($brands as $brand) {
            $dailyTimeSeries = $brand->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $brandToTimeSeriesMap[$brand->id] = $dailyTimeSeries->id;
            }
        }

        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                    'sales_org_currency_external' => 'sales_org_currency_external',
                    'invoices'                    => 'invoices',
                    'customers_invoiced'          => 'customers_invoiced',
                ],
                'brand_time_series_records',
                'brand_time_series_id',
                $from_date,
                $to_date,
            );
        }

        $groupCurrencyCode = $group->currency->code ?? 'GBP';

        $results = [];
        foreach ($brands as $brand) {
            $timeSeriesId = $brandToTimeSeriesMap[$brand->id] ?? null;
            $stats = $allStats[$timeSeriesId] ?? [];

            $results[] = array_merge($stats, [
                'id'                  => $brand->id,
                'slug'                => $brand->slug,
                'name'                => $brand->name,
                'group_currency_code' => $groupCurrencyCode,
            ]);
        }

        return $results;
    }
}
