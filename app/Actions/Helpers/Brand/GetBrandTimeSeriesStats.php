<?php

namespace App\Actions\Helpers\Brand;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Brand;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

class GetBrandTimeSeriesStats
{
    use AsObject;

    public function handle(Group|Organisation|Shop $parent, $from_date = null, $to_date = null): array
    {
        $groupId = match (true) {
            $parent instanceof Group        => $parent->id,
            $parent instanceof Organisation => $parent->group_id,
            $parent instanceof Shop        => $parent->group_id,
        };

        $brands = Brand::query()
            ->select(['id', 'slug', 'name', 'group_id'])
            ->where('group_id', $groupId)
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
            $additionalWhere = [];

            if ($parent instanceof Organisation) {
                $additionalWhere['organisation_id'] = $parent->id;
            } elseif ($parent instanceof Shop) {
                $additionalWhere['shop_id'] = $parent->id;
            }

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
                $additionalWhere
            );
        }

        $group             = $parent instanceof Group ? $parent : $parent->group;
        $groupCurrencyCode = $group?->currency?->code ?? 'GBP';

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
