<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget;

use App\Actions\Catalogue\Shop\SalesTarget\Concerns\HasOrdersPipeline;
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
            'expected'          => round($this->expected($salesSoFar, $lastYearSoFar, $lastYearTotal, $today), 2),
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
            ],
            'granularity'       => 'year',
            'can_edit'          => false,
            'update_route'      => null,
        ];
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
