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
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopSalesTarget;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    /**
     * @var array<string, list<array{0: string, 1: string, 2: float|null}>>
     */
    private array $yearDays = [];

    public function handle(Shop|Organisation|Group $parent, ?User $user = null, ?Carbon $today = null, bool $withChildren = true): array
    {
        $today = ($today ?? now('UTC'))->copy()->startOfDay();
        $sales = $this->salesData($parent, $today);

        return $this->yearBlock(
            $parent,
            $today,
            $sales,
            $this->salesShopIds($parent),
            $this->targetShopIds($parent),
            $this->currencyCode($parent),
            $withChildren ? $this->childBlocks($parent, $today, $sales) : []
        );
    }

    /**
     * Every figure the year and its children need, read once per shop for all of them, so the
     * number of queries does not grow with the number of shops or organisations.
     *
     * @return array{monthly: array<int, array<string, array{org: float, grp: float}>>, daily: array<int, array<string, array{org: float, grp: float}>>, invoices: array<int, int>, targets: Collection<int, ShopSalesTarget>, pipelines: array<string, array<int, array>>, forecasts: array<string, array<int, array>>}
     */
    private function salesData(Shop|Organisation|Group $parent, Carbon $today): array
    {
        $shopIds       = $this->salesShopIds($parent);
        $yearStart     = $today->copy()->startOfYear();
        $lastYearStart = $yearStart->copy()->subYear();
        $currencies    = $parent instanceof Group ? ['grp', 'org'] : ['org'];
        $targets       = ShopSalesTarget::whereIn('shop_id', $this->targetShopIds($parent))
            ->whereBetween('month', [$yearStart->toDateString(), $yearStart->copy()->endOfYear()->toDateString()])
            ->with($this->targetRelations($parent))
            ->get();

        foreach ($targets->whereNotNull('invoice_category_id')->groupBy(fn (ShopSalesTarget $target) => $target->month->month) as $month => $categoryTargets) {
            $monthStart = $yearStart->copy()->month($month);
            $this->categorySalesOfShops($categoryTargets->pluck('shop_id')->unique()->values()->all(), $monthStart, $monthStart->copy()->endOfMonth());
        }

        return [
            'monthly'   => $this->salesByShopAndPeriod($shopIds, TimeSeriesFrequencyEnum::MONTHLY, $lastYearStart->format('Y-m'), $yearStart->copy()->endOfYear()->format('Y-m')),
            'daily'     => $this->salesByShopAndPeriod($shopIds, TimeSeriesFrequencyEnum::DAILY, $lastYearStart->toDateString(), $today->toDateString()),
            'invoices'  => $this->invoiceCountsByShop($shopIds, $yearStart, $today),
            'targets'   => $targets,
            'pipelines' => array_combine($currencies, array_map(fn (string $currency) => $this->pipelineByShop($parent, $currency), $currencies)),
            'forecasts' => array_combine($currencies, array_map(fn (string $currency) => $this->salesForecastByShop($shopIds, $today, $currency), $currencies)),
        ];
    }

    /**
     * @return array<int, array<string, array{org: float, grp: float}>> shop id => period => invoiced sales in each currency (partners included)
     */
    private function salesByShopAndPeriod(array $shopIds, TimeSeriesFrequencyEnum $frequency, string $from, string $to): array
    {
        $byShop = [];
        $rows   = DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->whereIn('shop_time_series.shop_id', $shopIds)
            ->where('shop_time_series.frequency', $frequency->value)
            ->whereBetween('shop_time_series_records.period', [$from, $to])
            ->groupBy('shop_time_series.shop_id', 'shop_time_series_records.period')
            ->selectRaw('shop_time_series.shop_id, shop_time_series_records.period, sum('.$this->salesExpressionIn('org').') as org, sum('.$this->salesExpressionIn('grp').') as grp')
            ->get();

        foreach ($rows as $row) {
            $byShop[$row->shop_id][$row->period] = ['org' => (float) $row->org, 'grp' => (float) $row->grp];
        }

        return $byShop;
    }

    /**
     * The year of one parent (the dashboard's, or one of its shops or organisations) from the shared sales data.
     */
    private function yearBlock(Shop|Organisation|Group $parent, Carbon $today, array $sales, array $shopIds, array $targetShopIds, string $currencyCode, array $children): array
    {
        $currency      = $parent instanceof Group ? 'grp' : 'org';
        $yearStart     = $today->copy()->startOfYear();
        $monthOfYear   = $today->month;
        $lastYearStart = $yearStart->copy()->subYear();

        $lastYearMonthly = $this->monthlySales($sales, $shopIds, $lastYearStart, $currency);
        $thisYearMonthly = array_filter($this->monthlySales($sales, $shopIds, $yearStart, $currency), fn ($month) => $month < $monthOfYear, ARRAY_FILTER_USE_KEY);
        $thisYearMonthly[$monthOfYear] = $this->dailySalesTotal($sales, $shopIds, $today->copy()->startOfMonth(), $today, $currency);

        $lastYearMonthStart = $today->copy()->startOfMonth()->subYear();
        $lastYearSameDay    = $lastYearMonthStart->copy()->day(min($today->day, $lastYearMonthStart->daysInMonth));

        $salesSoFar    = array_sum($thisYearMonthly);
        $lastYearSoFar = array_sum(array_filter($lastYearMonthly, fn ($month) => $month < $monthOfYear, ARRAY_FILTER_USE_KEY))
            + $this->dailySalesTotal($sales, $shopIds, $lastYearMonthStart, $lastYearSameDay, $currency);
        $lastYearTotal = array_sum($lastYearMonthly);

        $targets            = $sales['targets']->whereIn('shop_id', $targetShopIds)->values();
        $targetsByShopMonth = $targets->groupBy(fn (ShopSalesTarget $target) => $target->shop_id.'-'.$target->month->month);

        $growth       = (float) config('marketing.default_sales_target_growth');
        $targetAmount = 0.0;
        foreach ($targetShopIds as $shopId) {
            $lastYearMonthlyOfShop = $this->monthlySales($sales, [$shopId], $lastYearStart, $currency);
            foreach (range(1, 12) as $month) {
                $targetAmount += $this->shopTarget($parent, $shopId, $yearStart->copy()->month($month), $targetsByShopMonth->get($shopId.'-'.$month, collect()), $lastYearMonthlyOfShop[$month] ?? 0, $growth);
            }
        }
        $targetAmount = $targetAmount > 0 ? round($targetAmount, 2) : null;

        $restOfYear    = $this->restOfYearByMonth($sales, $shopIds, $targetShopIds, $today, $currency);
        $lastSetTarget = $targets->sortByDesc('updated_at')->first();
        $pipeline      = $this->sumPipelines(array_intersect_key($sales['pipelines'][$currency], array_flip($shopIds)));
        $remainingDays = $today->daysInYear - $today->dayOfYear;
        $gap           = $targetAmount === null ? null : max(0, $targetAmount - $salesSoFar - $pipeline['amount']);

        return [
            'month'             => $yearStart->format('Y-m'),
            'month_label'       => __(':year year to date', ['year' => $yearStart->format('Y')]),
            'last_year_label'   => $lastYearStart->format('Y'),
            'currency_code'     => $currencyCode,
            'day_of_month'      => $today->dayOfYear,
            'days_in_month'     => $today->daysInYear,
            'sales_so_far'      => round($salesSoFar, 2),
            'invoices'          => $this->sumInvoiceCounts($sales['invoices'], $shopIds),
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
                'weekly_versus_last_year' => $this->weeklyVersusLastYear($sales, $shopIds, $yearStart, $today, $currency),
                'forecast'  => $restOfYear !== null ? $this->yearForecastLine($salesSoFar, $thisYearMonthly[$monthOfYear], $monthOfYear, $restOfYear) : null,
            ],
            'children'          => $children,
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
    private function restOfYearByMonth(array $sales, array $shopIds, array $openShopIds, Carbon $today, string $currency): ?array
    {
        $forecastByShop = array_intersect_key($sales['forecasts'][$currency], array_flip($shopIds));
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
            $runRate = $this->dailySalesTotal($sales, $withoutForecast, $today->copy()->startOfYear(), $today, $currency) / $today->dayOfYear;
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
     * Monthly periods are stored as 'Y-m' strings.
     *
     * @return array<int, float> month of year => invoiced sales of the shops (partners included)
     */
    private function monthlySales(array $sales, array $shopIds, Carbon $yearStart, string $currency): array
    {
        $year    = $yearStart->format('Y');
        $monthly = [];
        foreach ($shopIds as $shopId) {
            foreach ($sales['monthly'][$shopId] ?? [] as $period => $amounts) {
                if (str_starts_with($period, $year)) {
                    $month           = (int) substr($period, 5, 2);
                    $monthly[$month] = ($monthly[$month] ?? 0) + $amounts[$currency];
                }
            }
        }

        return $monthly;
    }

    /**
     * The year of each open shop of an organisation, or of each organisation of a group, keyed like the month's children.
     *
     * @return list<array<string, mixed>>
     */
    private function childBlocks(Shop|Organisation|Group $parent, Carbon $today, array $sales): array
    {
        if ($parent instanceof Organisation) {
            $currencyCode = $this->currencyCode($parent);

            return Shop::whereIn('id', $this->targetShopIds($parent))->get()->map(fn (Shop $shop) => [
                ...$this->yearBlock($shop, $today, $sales, [$shop->id], [$shop->id], $currencyCode, []),
                'key'  => (string) $shop->id,
                'name' => $shop->name,
            ])->values()->all();
        }

        if (!$parent instanceof Group) {
            return [];
        }

        $shopsByOrganisation = Shop::whereIn('id', $this->salesShopIds($parent))->get(['id', 'organisation_id', 'state'])->groupBy('organisation_id');

        return $parent->organisations()->whereHas('shops', fn ($query) => $query->where('state', '!=', ShopStateEnum::CLOSED))->with('currency')->get()
            ->map(function (Organisation $organisation) use ($today, $sales, $shopsByOrganisation) {
                $shops = $shopsByOrganisation->get($organisation->id, collect());

                return [
                    ...$this->yearBlock(
                        $organisation,
                        $today,
                        $sales,
                        $shops->pluck('id')->all(),
                        $shops->filter(fn (Shop $shop) => $shop->state !== ShopStateEnum::CLOSED)->pluck('id')->all(),
                        $organisation->currency->code,
                        []
                    ),
                    'key'  => (string) $organisation->id,
                    'name' => $organisation->name,
                ];
            })->values()->all();
    }

    /**
     * Each week's year-to-date sales against the same days last year, ending today.
     *
     * @return array<int, array{x: float, y: float|null}> x is the position on the month axis (Jan ends at 1)
     */
    private function weeklyVersusLastYear(array $sales, array $shopIds, Carbon $yearStart, Carbon $today, string $currency): array
    {
        $daily = $this->dailySales($sales, $shopIds, $currency);

        $points        = [];
        $thisYearSoFar = 0.0;
        $lastYearSoFar = 0.0;
        foreach ($this->yearDays($yearStart, $today) as [$date, $lastYearDate, $x]) {
            $thisYearSoFar += $daily[$date] ?? 0;
            $lastYearSoFar += $daily[$lastYearDate] ?? 0;
            if ($x !== null) {
                $points[] = [
                    'x' => $x,
                    'y' => $lastYearSoFar > 0 ? round(($thisYearSoFar / $lastYearSoFar - 1) * 100, 1) : null,
                ];
            }
        }

        return $points;
    }

    /**
     * Each day of the year until today with the same day last year, and its position on the month
     * axis when it ends a week (or is today), worked out once for the year and all its children.
     *
     * @return list<array{0: string, 1: string, 2: float|null}>
     */
    private function yearDays(Carbon $yearStart, Carbon $today): array
    {
        return $this->yearDays[$today->toDateString()] ??= (function () use ($yearStart, $today) {
            $days = [];
            for ($day = $yearStart->copy(); $day->lte($today); $day->addDay()) {
                $days[] = [
                    $day->toDateString(),
                    $day->copy()->subYear()->toDateString(),
                    $day->dayOfYear % 7 === 0 || $day->eq($today) ? round($day->month - 1 + $day->day / $day->daysInMonth, 3) : null,
                ];
            }

            return $days;
        })();
    }

    /**
     * @return array<string, float> date => invoiced sales of the shops, from the start of last year until today
     */
    private function dailySales(array $sales, array $shopIds, string $currency): array
    {
        $daily = [];
        foreach ($shopIds as $shopId) {
            foreach ($sales['daily'][$shopId] ?? [] as $date => $amounts) {
                $daily[$date] = ($daily[$date] ?? 0) + $amounts[$currency];
            }
        }

        return $daily;
    }

    private function dailySalesTotal(array $sales, array $shopIds, Carbon $from, Carbon $to, string $currency): float
    {
        $from  = $from->toDateString();
        $to    = $to->toDateString();
        $total = 0.0;
        foreach ($shopIds as $shopId) {
            foreach ($sales['daily'][$shopId] ?? [] as $date => $amounts) {
                if ($date >= $from && $date <= $to) {
                    $total += $amounts[$currency];
                }
            }
        }

        return $total;
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
