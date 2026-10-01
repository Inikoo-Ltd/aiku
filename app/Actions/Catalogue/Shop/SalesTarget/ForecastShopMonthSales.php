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
 * Forecasts each open shop's invoiced sales, partners included, for every day left in the month,
 * in the organisation's and the group's currency, into shop_stats.sales_forecast. The month
 * target block adds it to the sales so far as the expected month end. Backtested on twelve
 * months of 21 shops it was off by half as much as last year's pattern scaled to this month.
 */
class ForecastShopMonthSales
{
    use AsAction;

    public string $commandSignature = 'hydrate:shop-sales-forecast';

    private const int HISTORY_DAYS = 1024;

    private const int MINIMUM_HISTORY_DAYS = 28;

    /**
     * @return int shops forecast
     */
    public function handle(?Carbon $today = null): int
    {
        $today     = ($today ?? now('UTC'))->copy()->startOfDay();
        $firstDay  = $today->copy()->subDays(self::HISTORY_DAYS);
        $daysAhead = $today->daysInMonth - $today->day + 1;
        $shopIds   = Shop::where('state', '!=', ShopStateEnum::CLOSED)->pluck('id')->all();

        $series = [];
        foreach ($this->dailySales($shopIds, $firstDay, $today->copy()->subDay()) as $shopId => $currencies) {
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

        $forecast = ForecastWithTimesFm::run($series, $daysAhead);
        if ($forecast === null) {
            return 0;
        }

        $days     = range($today->day, $today->daysInMonth);
        $byShop   = [];
        foreach ($forecast['deciles'] as $key => $deciles) {
            [$shopId, $currency]          = explode(':', $key);
            $byShop[$shopId][$currency] = array_combine($days, array_map(fn (float $value) => round($value, 2), ForecastWithTimesFm::expectedValues($deciles)));
        }

        foreach ($byShop as $shopId => $currencies) {
            ShopStats::where('shop_id', $shopId)->update([
                'sales_forecast'             => json_encode([
                    'version' => $forecast['version'],
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
        $command->info("Forecast the rest of the month for $count shops");

        return $count > 0 || !config('services.timesfm.url') ? 0 : 1;
    }
}
