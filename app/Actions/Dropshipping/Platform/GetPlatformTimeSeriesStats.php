<?php

namespace App\Actions\Dropshipping\Platform;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\SalesChannel;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPlatformTimeSeriesStats
{
    use AsObject;

    public function handle(Group|Organisation|Shop $parent, $from_date = null, $to_date = null): array
    {
        $groupId = match (true) {
            $parent instanceof Group        => $parent->id,
            $parent instanceof Organisation => $parent->group_id,
            $parent instanceof Shop        => $parent->group_id,
        };

        $platforms = Platform::query()
            ->select(['id', 'slug', 'code', 'name', 'type', 'group_id'])
            ->where('group_id', $groupId)
            ->with([
                'timeSeries' => fn ($q) => $q->select(['id', 'platform_id', 'frequency'])
                    ->where('frequency', TimeSeriesFrequencyEnum::DAILY->value),
            ])
            ->get();

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
                    'sales_external'              => 'sales_external',
                    'sales_org_currency_external' => 'sales_org_currency_external',
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                    'invoices'                    => 'invoices',
                    'channels'                    => 'channels',
                    'customers'                   => 'customers',
                    'portfolios'                  => 'portfolios',
                    'customer_clients'            => 'customer_clients',
                ],
                'platform_time_series_records',
                'platform_time_series_id',
                $from_date,
                $to_date,
                $additionalWhere
            );
        }

        $totalSalesByInterval = $this->calculateTotalSales($allStats, $parent);

        $organisationSlug = $parent instanceof Organisation ? $parent->slug : null;
        $shopSlug         = $parent instanceof Shop ? $parent->slug : null;
        if ($parent instanceof Shop) {
            $organisationSlug = $parent->organisation->slug ?? null;
        }

        $results = [];
        foreach ($platforms as $platform) {
            $timeSeriesId = $platformToTimeSeriesMap[$platform->id] ?? null;
            $stats        = $allStats[$timeSeriesId] ?? [];

            if (empty($stats) || collect($stats)->every(fn ($value) => $value == 0)) {
                continue;
            }

            $statsWithPercentage = $this->addSalesPercentage($stats, $totalSalesByInterval, $parent);

            $platformData = array_merge($statsWithPercentage, [
                'id'   => $platform->id,
                'slug' => $platform->slug,
                'code' => $platform->code,
                'name' => $platform->name,
            ]);

            if ($parent instanceof Shop) {
                $platformData['shop_id']           = $parent->id;
                $platformData['shop_slug']         = $shopSlug;
                $platformData['organisation_slug'] = $organisationSlug;
            } elseif ($parent instanceof Organisation) {
                $platformData['organisation_slug'] = $organisationSlug;
            }

            $results[] = $platformData;

            if ($parent instanceof Shop && $platform->type === PlatformTypeEnum::MANUAL) {
                $results = [
                    ...$results,
                    ...$this->manualSalesChannelChildren($timeSeriesId, $platform->slug, $parent, $totalSalesByInterval, $from_date, $to_date)
                ];
            }
        }

        return $results;
    }

    /**
     * Child rows under the manual platform, one per sales channel with invoices (Web, API, ...),
     * read from the pre-aggregated platform_sales_channel_time_series_records so the dashboard
     * never scans orders. Rows are flagged is_sales_channel so resources and totals can tell
     * them apart from platform rows.
     */
    private function manualSalesChannelChildren(int $timeSeriesId, string $platformSlug, Shop $shop, array $totalSalesByInterval, $from_date, $to_date): array
    {
        $channelIds = DB::table('platform_sales_channel_time_series_records')
            ->where('platform_time_series_id', $timeSeriesId)
            ->where('shop_id', $shop->id)
            ->distinct()
            ->pluck('sales_channel_id');

        if ($channelIds->isEmpty()) {
            return [];
        }

        $stats = CalculateTimeSeriesStats::run(
            $channelIds->all(),
            [
                'sales_external'              => 'sales_external',
                'sales_org_currency_external' => 'sales_org_currency_external',
                'sales_grp_currency_external' => 'sales_grp_currency_external',
                'invoices'                    => 'invoices',
            ],
            'platform_sales_channel_time_series_records',
            'sales_channel_id',
            $from_date,
            $to_date,
            [
                'platform_time_series_id' => $timeSeriesId,
                'shop_id'                 => $shop->id,
            ]
        );

        $channels = SalesChannel::whereIn('id', $channelIds)->get()->keyBy('id');

        $children = [];
        foreach ($channelIds as $channelId) {
            $channelStats = $stats[$channelId] ?? [];

            if (empty($channelStats) || collect($channelStats)->every(fn ($value) => $value == 0)) {
                continue;
            }

            $channel = $channels[$channelId] ?? null;

            $children[] = array_merge(
                $this->addSalesPercentage($channelStats, $totalSalesByInterval, $shop),
                [
                    'id'               => $channelId,
                    'slug'             => 'manual-channel-'.($channel->code ?? $channelId),
                    'name'             => $channel?->type === SalesChannelTypeEnum::WEBSITE ? __('Web') : ($channel->name ?? (string) $channelId),
                    'is_sales_channel' => true,
                    'parent_slug'      => $platformSlug,
                ]
            );
        }

        return $children;
    }

    private function calculateTotalSales(array $allStats, Group|Organisation|Shop $parent): array
    {
        $totals    = [];
        $intervals = DateIntervalEnum::cases();

        $salesField = match (true) {
            $parent instanceof Shop         => 'sales_grp_currency_external',
            $parent instanceof Organisation => 'sales_grp_currency_external',
            $parent instanceof Group        => 'sales_grp_currency_external',
        };

        foreach ($intervals as $interval) {
            $key          = $salesField . '_' . $interval->value;
            $totals[$key] = 0;

            foreach ($allStats as $stats) {
                $totals[$key] += (float)($stats[$key] ?? 0);
            }
        }

        return $totals;
    }

    private function addSalesPercentage(array $stats, array $totalSalesByInterval, Group|Organisation|Shop $parent): array
    {
        $intervals = DateIntervalEnum::cases();

        $salesField = match (true) {
            $parent instanceof Shop         => 'sales_grp_currency_external',
            $parent instanceof Organisation => 'sales_grp_currency_external',
            $parent instanceof Group        => 'sales_grp_currency_external',
        };

        foreach ($intervals as $interval) {
            $key          = $salesField . '_' . $interval->value;
            $totalSales   = $totalSalesByInterval[$key] ?? 0;
            $platformSales = (float)($stats[$key] ?? 0);

            $stats['sales_percentage_' . $interval->value] = $totalSales > 0
                ? round(($platformSales / $totalSales) * 100, 2)
                : 0;
        }

        return $stats;
    }
}
