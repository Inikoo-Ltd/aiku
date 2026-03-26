<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\SalesChannel;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Ordering\SalesChannel;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSalesChannelTimeSeriesStats
{
    use AsObject;

    public function handle(Group $group, $from_date = null, $to_date = null): array
    {
        $salesChannels = SalesChannel::query()
            ->select(['id', 'slug', 'name', 'group_id'])
            ->where('group_id', $group->id)
            ->where('type', SalesChannelTypeEnum::MARKETPLACE)
            ->where('show_in_dashboard', true)
            ->with([
                'timeSeries' => fn ($q) => $q->select(['id', 'sales_channel_id', 'frequency'])
                    ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value),
            ])
            ->get();

        $timeSeriesIds = [];
        $salesChannelToTimeSeriesMap = [];

        foreach ($salesChannels as $salesChannel) {
            $dailyTimeSeries = $salesChannel->timeSeries->first();
            if ($dailyTimeSeries) {
                $timeSeriesIds[] = $dailyTimeSeries->id;
                $salesChannelToTimeSeriesMap[$salesChannel->id] = $dailyTimeSeries->id;
            }
        }

        $allStats = [];
        if (!empty($timeSeriesIds)) {
            $allStats = CalculateTimeSeriesStats::run(
                $timeSeriesIds,
                [
                    'refunds'                     => 'refunds',
                    'invoices'                    => 'invoices',
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                ],
                'sales_channel_time_series_records',
                'sales_channel_time_series_id',
                $from_date,
                $to_date,
            );
        }

        $groupCurrencyCode = $group->currency->code ?? 'GBP';

        $results = [];
        foreach ($salesChannels as $salesChannel) {
            $timeSeriesId = $salesChannelToTimeSeriesMap[$salesChannel->id] ?? null;
            $stats        = $allStats[$timeSeriesId] ?? [];

            if (empty($stats) || collect($stats)->every(fn ($value) => $value == 0)) {
                continue;
            }

            $results[] = array_merge($stats, [
                'id'                  => $salesChannel->id,
                'slug'                => $salesChannel->slug,
                'name'                => $salesChannel->name,
                'group_currency_code' => $groupCurrencyCode,
            ]);
        }

        return $results;
    }
}
