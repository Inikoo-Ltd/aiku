<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget;

use App\Actions\Catalogue\Shop\SalesTarget\Concerns\HasOrdersPipeline;
use App\Actions\Catalogue\Shop\SalesTarget\Concerns\HasSalesForecast;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopSalesTarget;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Year-to-date sales against target, in the same shape as GetShopMonthSalesTarget. The year
 * target is the sum of the twelve monthly targets. Last year is compared like for like: whole
 * months before this one, plus the same days of this month.
 */
class GetShopYearSalesTarget
{
    use AsObject;
    use HasOrdersPipeline;
    use HasSalesForecast;

    public function handle(Shop|Organisation|Group $parent, ?User $user = null, ?Carbon $today = null): array
    {
        $today         = ($today ?? now('UTC'))->copy()->startOfDay();
        $yearStart     = $today->copy()->startOfYear();
        $monthOfYear   = $today->month;
        $lastYearStart = $yearStart->copy()->subYear();

        $shopIds         = $this->salesShopIds($parent);
        $salesExpression = $this->salesExpression($parent);
        $lastYearMonthly = $this->monthlySales($shopIds, $lastYearStart, $salesExpression);
        $thisYearMonthly = array_filter($this->monthlySales($shopIds, $yearStart, $salesExpression), fn ($month) => $month < $monthOfYear, ARRAY_FILTER_USE_KEY);
        $thisYearMonthly[$monthOfYear] = $this->dailySalesTotal($shopIds, $today->copy()->startOfMonth(), $today, $salesExpression);

        $lastYearMonthStart = $today->copy()->startOfMonth()->subYear();
        $lastYearSameDay    = $lastYearMonthStart->copy()->day(min($today->day, $lastYearMonthStart->daysInMonth));

        $salesSoFar    = array_sum($thisYearMonthly);
        $lastYearSoFar = array_sum(array_filter($lastYearMonthly, fn ($month) => $month < $monthOfYear, ARRAY_FILTER_USE_KEY))
            + $this->dailySalesTotal($shopIds, $lastYearMonthStart, $lastYearSameDay, $salesExpression);
        $lastYearTotal = array_sum($lastYearMonthly);

        $targetShopIds = $this->targetShopIds($parent);
        $targets       = ShopSalesTarget::whereIn('shop_id', $targetShopIds)
            ->whereBetween('month', [$yearStart->toDateString(), $yearStart->copy()->endOfYear()->toDateString()])
            ->with($this->targetRelations($parent))
            ->get();
        $targetsByShopMonth = $targets->groupBy(fn (ShopSalesTarget $target) => $target->shop_id.'-'.$target->month->month);

        $growth               = (float) config('marketing.default_sales_target_growth');
        $lastYearMonthlyShops = $this->monthlySalesByShop($targetShopIds, $lastYearStart, $salesExpression);

        $targetAmount = 0.0;
        foreach ($targetShopIds as $shopId) {
            foreach (range(1, 12) as $month) {
                $targetAmount += $this->shopTarget($parent, $shopId, $yearStart->copy()->month($month), $targetsByShopMonth->get($shopId.'-'.$month, collect()), $lastYearMonthlyShops[$shopId][$month] ?? 0, $growth);
            }
        }
        $targetAmount = $targetAmount > 0 ? round($targetAmount, 2) : null;

        $restOfYear    = $this->restOfYearByMonth($shopIds, $targetShopIds, $today, $salesExpression, $parent instanceof Group ? 'grp' : 'org');
        $lastSetTarget = $targets->sortByDesc('updated_at')->first();
        $pipeline      = $this->pipeline($parent);
        $remainingDays = $today->daysInYear - $today->dayOfYear;
        $gap           = $targetAmount === null ? null : max(0, $targetAmount - $salesSoFar - $pipeline['amount']);

        return [
            'month'             => $yearStart->format('Y-m'),
            'month_label'       => __(':year year to date', ['year' => $yearStart->format('Y')]),
            'last_year_label'   => $lastYearStart->format('Y'),
            'currency_code'     => $this->currencyCode($parent),
            'day_of_month'      => $today->dayOfYear,
            'days_in_month'     => $today->daysInYear,
            'sales_so_far'      => round($salesSoFar, 2),
            'last_year_so_far'  => round($lastYearSoFar, 2),
            'last_year_total'   => round($lastYearTotal, 2),
            'expected'          => round($restOfYear !== null ? $salesSoFar + array_sum(array_column($restOfYear, 0)) : $this->expected($salesSoFar, $lastYearSoFar, $lastYearTotal, $today), 2),
            'pipeline'          => $pipeline,
            'target'            => [
                'amount'      => $targetAmount,
                'is_default'  => $targets->isEmpty(),
                'months_set'  => $targetsByShopMonth->count(),
                'is_sum_of_shops' => !$parent instanceof Shop,
                'growth'      => $growth,
                'set_by'      => $lastSetTarget?->setBy?->contact_name,
                'set_at'      => $lastSetTarget?->updated_at,
            ],
            'gap'               => $gap === null ? null : round($gap, 2),
            'needed_per_day'    => $gap === null ? null : round($remainingDays > 0 ? $gap / $remainingDays : $gap, 2),
            'remaining_days'    => $remainingDays,
            'chart'             => [
                'days'      => range(1, 12),
                'this_year' => $this->cumulative($thisYearMonthly, $monthOfYear),
                'last_year' => $this->cumulative($lastYearMonthly, 12),
                'weekly_versus_last_year' => $this->weeklyVersusLastYear($shopIds, $yearStart, $today, $salesExpression),
                'forecast'  => $restOfYear !== null ? $this->yearForecastLine($salesSoFar, $thisYearMonthly[$monthOfYear], $monthOfYear, $restOfYear) : null,
            ],
            'granularity'       => 'year',
            'can_edit'          => false,
            'update_route'      => null,
        ];
    }

    /**
     * The forecast for the rest of the year by month, from the shops' nightly forecasts. A closed
     * shop adds nothing and an open shop without a forecast yet adds its run rate this year. Null
     * when no shop has a forecast, so the block keeps the last-year pattern.
     *
     * @return array<int, array{0: float, 1: float}>|null month of year => [expected, variance]
     */
    private function restOfYearByMonth(array $shopIds, array $openShopIds, Carbon $today, string $salesExpression, string $currency): ?array
    {
        $forecastByShop = $this->salesForecastByShop($shopIds, $today, $currency);
        if (!$forecastByShop) {
            return null;
        }

        $rest = array_fill_keys(range($today->month, 12), [0.0, 0.0]);
        foreach ($forecastByShop as $days) {
            foreach ($days as $date => [$expected, $variance]) {
                $month        = (int) substr($date, 5, 2);
                $rest[$month] = [$rest[$month][0] + $expected, $rest[$month][1] + $variance];
            }
        }

        $withoutForecast = array_values(array_diff($openShopIds, array_keys($forecastByShop)));
        if ($withoutForecast) {
            $runRate = $this->dailySalesTotal($withoutForecast, $today->copy()->startOfYear(), $today, $salesExpression) / $today->dayOfYear;
            for ($day = $today->copy()->addDay(); $day->year === $today->year; $day->addDay()) {
                $rest[$day->month][0] += $runRate;
            }
        }

        return $rest;
    }

    /**
     * From last month's actual total: this month's sales so far plus the rest of it, then each month ahead.
     *
     * @param  array<int, array{0: float, 1: float}>  $restOfYear
     *
     * @return array{expected: list<float|null>, low: list<float|null>, high: list<float|null>}
     */
    private function yearForecastLine(float $salesSoFar, float $thisMonthSoFar, int $monthOfYear, array $restOfYear): array
    {
        $restOfYear[$monthOfYear][0] += $thisMonthSoFar;

        return $this->forecastLine($salesSoFar - $thisMonthSoFar, $monthOfYear - 1, $restOfYear, 12, self::YEAR_BAND_FACTOR);
    }

    /**
     * Monthly periods are stored as 'Y-m' strings, so the range must be compared in that format.
     *
     * @return array<int, float> month of year => invoiced sales (org currency, partners included)
     */
    private function monthlySales(array $shopIds, Carbon $yearStart, string $salesExpression): array
    {
        return $this->monthlyRecords($shopIds, $yearStart)
            ->groupBy('shop_time_series_records.period')
            ->selectRaw("shop_time_series_records.period, sum($salesExpression) as sales")
            ->pluck('sales', 'period')
            ->mapWithKeys(fn ($sales, $period) => [(int) substr($period, 5, 2) => (float) $sales])
            ->all();
    }

    /**
     * @return array<int, array<int, float>> shop id => month of year => invoiced sales
     */
    private function monthlySalesByShop(array $shopIds, Carbon $yearStart, string $salesExpression): array
    {
        $byShop = [];
        foreach ($this->monthlyRecords($shopIds, $yearStart)->selectRaw("shop_time_series.shop_id, shop_time_series_records.period, $salesExpression as sales")->get() as $record) {
            $byShop[$record->shop_id][(int) substr($record->period, 5, 2)] = (float) $record->sales;
        }

        return $byShop;
    }

    private function monthlyRecords(array $shopIds, Carbon $yearStart): Builder
    {
        return DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->whereIn('shop_time_series.shop_id', $shopIds)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->whereBetween('shop_time_series_records.period', [$yearStart->format('Y-m'), $yearStart->copy()->endOfYear()->format('Y-m')]);
    }

    /**
     * Each week's year-to-date sales against the same days last year, ending today.
     *
     * @return array<int, array{x: float, y: float|null}> x is the position on the month axis (Jan ends at 1)
     */
    private function weeklyVersusLastYear(array $shopIds, Carbon $yearStart, Carbon $today, string $salesExpression): array
    {
        $thisYearDaily = $this->dailySales($shopIds, $yearStart, $today, $salesExpression);
        $lastYearDaily = $this->dailySales($shopIds, $yearStart->copy()->subYear(), $today->copy()->subYear(), $salesExpression);

        $points        = [];
        $thisYearSoFar = 0.0;
        $lastYearSoFar = 0.0;
        for ($day = $yearStart->copy(); $day->lte($today); $day->addDay()) {
            $thisYearSoFar += $thisYearDaily[$day->toDateString()] ?? 0;
            $lastYearSoFar += $lastYearDaily[$day->copy()->subYear()->toDateString()] ?? 0;
            if ($day->dayOfYear % 7 === 0 || $day->eq($today)) {
                $points[] = [
                    'x' => round($day->month - 1 + $day->day / $day->daysInMonth, 3),
                    'y' => $lastYearSoFar > 0 ? round(($thisYearSoFar / $lastYearSoFar - 1) * 100, 1) : null,
                ];
            }
        }

        return $points;
    }

    /**
     * @return array<string, float> date => invoiced sales
     */
    private function dailySales(array $shopIds, Carbon $from, Carbon $to, string $salesExpression): array
    {
        return DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->whereIn('shop_time_series.shop_id', $shopIds)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereBetween('shop_time_series_records.period', [$from->toDateString(), $to->toDateString()])
            ->groupBy('shop_time_series_records.period')
            ->selectRaw("shop_time_series_records.period, sum($salesExpression) as sales")
            ->pluck('sales', 'period')
            ->map(fn ($sales) => (float) $sales)
            ->all();
    }

    private function dailySalesTotal(array $shopIds, Carbon $from, Carbon $to, string $salesExpression): float
    {
        return (float) DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->whereIn('shop_time_series.shop_id', $shopIds)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereBetween('shop_time_series_records.period', [$from->toDateString(), $to->toDateString()])
            ->sum(DB::raw($salesExpression));
    }

    private function cumulative(array $monthly, int $untilMonth): array
    {
        $runningTotal = 0;
        $series       = [];
        for ($month = 1; $month <= $untilMonth; $month++) {
            $runningTotal += $monthly[$month] ?? 0;
            $series[]     = round($runningTotal, 2);
        }

        return $series;
    }

    /**
     * The rest of last year, scaled by how this year is running against the same days last year.
     * With no history for those days it falls back to the current daily run rate.
     */
    private function expected(float $salesSoFar, float $lastYearSoFar, float $lastYearTotal, Carbon $today): float
    {
        if ($lastYearSoFar <= 0) {
            return $salesSoFar / $today->dayOfYear * $today->daysInYear;
        }

        return $salesSoFar + ($lastYearTotal - $lastYearSoFar) * ($salesSoFar / $lastYearSoFar);
    }
}
