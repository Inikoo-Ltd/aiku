<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget;

use App\Actions\Helpers\Forecast\ForecastWithTimesFm;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopStats;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Forecasts each open shop's invoiced sales, partners included, for every day left in the year,
 * in the organisation's and the group's currency, into shop_stats.sales_forecast as
 * date => [expected, variance]. The rest of this month comes from a daily forecast and the months
 * after from a weekly one, spread evenly over its days: backtested on 2025, the daily forecast
 * halved the month-end error of last year's pattern and the weekly one cut the year-end error by
 * a third, where a daily forecast that far ahead fell short of the November and December peak.
 */
class ForecastShopSales
{
    use AsAction;

    public string $commandSignature = 'hydrate:shop-sales-forecast';

    private const int HISTORY_DAYS = 1022;

    private const int MINIMUM_HISTORY_DAYS = 28;

    /**
     * @return int shops forecast
     */
    public function handle(?Carbon $today = null): int
    {
        $today        = ($today ?? now('UTC'))->copy()->startOfDay();
        $daysOfMonth  = $today->daysInMonth - $today->day + 1;
        $daysAfter    = (int) $today->copy()->endOfMonth()->startOfDay()->diffInDays($today->copy()->endOfYear()->startOfDay());
        $weeksAfter   = (int) ceil(($daysOfMonth + $daysAfter) / 7);
        $history      = $this->histories($today);

        $daily = ForecastWithTimesFm::run($history, $daysOfMonth);
        if ($daily === null) {
            return 0;
        }
        $weekly = $daysAfter > 0 ? ForecastWithTimesFm::run(array_map(fn (array $days) => $this->weeks($days), $history), $weeksAfter) : null;
        if ($daysAfter > 0 && $weekly === null) {
            return 0;
        }

        $byShop = [];
        foreach ($daily['deciles'] as $key => $dailyDeciles) {
            [$shopId, $currency] = explode(':', $key);

            $days = [];
            foreach ($this->expectedWithVariance($dailyDeciles) as $offset => $day) {
                $days[$today->copy()->addDays($offset)->toDateString()] = $day;
            }
            if ($weekly !== null) {
                foreach ($this->expectedWithVariance($weekly['deciles'][$key]) as $week => [$expected, $variance]) {
                    for ($offset = $week * 7; $offset < $week * 7 + 7; $offset++) {
                        if ($offset >= $daysOfMonth && $offset < $daysOfMonth + $daysAfter) {
                            $days[$today->copy()->addDays($offset)->toDateString()] = [round($expected / 7, 2), round($variance / 7, 2)];
                        }
                    }
                }
            }
            $byShop[$shopId][$currency] = $days;
        }

        foreach ($byShop as $shopId => $currencies) {
            ShopStats::where('shop_id', $shopId)->update([
                'sales_forecast'             => json_encode([
                    'version' => $daily['version'],
                    'from'    => $today->toDateString(),
                    'org'     => $currencies['org'] ?? null,
                    'grp'     => $currencies['grp'] ?? null,
                ]),
                'sales_forecast_hydrated_at' => now(),
            ]);
        }

        return count($byShop);
    }

    /**
     * @param  list<list<float>>  $deciles
     *
     * @return list<array{0: float, 1: float}> expected value and variance of each step, the variance read from the 10th to 90th percentile spread
     */
    private function expectedWithVariance(array $deciles): array
    {
        return array_map(
            fn (float $expected, array $step) => [round($expected, 2), round(((max(0.0, (float) $step[8]) - max(0.0, (float) $step[0])) / 2.563) ** 2, 2)],
            ForecastWithTimesFm::expectedValues($deciles),
            $deciles
        );
    }

    /**
     * @param  list<float>  $days
     *
     * @return list<float> sums of seven days, the last week ending yesterday
     */
    private function weeks(array $days): array
    {
        return array_map('array_sum', array_chunk(array_slice($days, count($days) % 7), 7));
    }

    /**
     * Daily sales up to yesterday of every open shop with four weeks or more of selling, from its first sale.
     *
     * @return array<string, list<float>> "shop id:currency" => sales per day, oldest first
     */
    private function histories(Carbon $today): array
    {
        $firstDay = $today->copy()->subDays(self::HISTORY_DAYS);
        $series   = [];
        foreach ($this->dailySales(Shop::where('state', '!=', ShopStateEnum::CLOSED)->pluck('id')->all(), $firstDay, $today->copy()->subDay()) as $shopId => $currencies) {
            foreach ($currencies as $currency => $sales) {
                $sellingDays = array_keys(array_filter($sales));
                if (!$sellingDays) {
                    continue;
                }
                $history = array_fill(0, self::HISTORY_DAYS, 0.0);
                foreach ($sales as $offset => $amount) {
                    $history[$offset] = $amount;
                }
                $firstSale = min($sellingDays);
                if (self::HISTORY_DAYS - $firstSale >= self::MINIMUM_HISTORY_DAYS) {
                    $series["$shopId:$currency"] = array_slice($history, $firstSale);
                }
            }
        }

        return $series;
    }

    /**
     * @return array<int, array{org: array<int, float>, grp: array<int, float>}> shop id => currency => day offset => sales
     */
    private function dailySales(array $shopIds, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->whereIn('shop_time_series.shop_id', $shopIds)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereBetween('shop_time_series_records.period', [$from->toDateString(), $to->toDateString()])
            ->groupBy('shop_time_series.shop_id', 'shop_time_series_records.period')
            ->selectRaw('shop_time_series.shop_id, shop_time_series_records.period')
            ->selectRaw('sum(shop_time_series_records.sales_org_currency_external + coalesce(shop_time_series_records.sales_org_currency_internal, 0)) as org')
            ->selectRaw('sum(shop_time_series_records.sales_grp_currency_external + coalesce(shop_time_series_records.sales_grp_currency_internal, 0)) as grp')
            ->get();

        $sales = [];
        foreach ($rows as $row) {
            $offset = (int) $from->diffInDays(Carbon::parse($row->period));
            foreach (['org', 'grp'] as $currency) {
                $sales[$row->shop_id][$currency][$offset] = (float) $row->$currency;
            }
        }

        return $sales;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $count = $this->handle();
        $command->info("Forecast the rest of the year for $count shops");

        return $count > 0 || !config('services.timesfm.url') ? 0 : 1;
    }
}
