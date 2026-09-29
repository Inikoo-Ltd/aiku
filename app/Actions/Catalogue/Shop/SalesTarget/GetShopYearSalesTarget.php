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
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
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

    public function handle(Shop $shop, ?User $user = null, ?Carbon $today = null): array
    {
        $today         = ($today ?? now('UTC'))->copy()->startOfDay();
        $yearStart     = $today->copy()->startOfYear();
        $monthOfYear   = $today->month;
        $lastYearStart = $yearStart->copy()->subYear();

        $lastYearMonthly = $this->monthlySales($shop, $lastYearStart);
        $thisYearMonthly = array_filter($this->monthlySales($shop, $yearStart), fn ($month) => $month < $monthOfYear, ARRAY_FILTER_USE_KEY);
        $thisYearMonthly[$monthOfYear] = $this->dailySalesTotal($shop, $today->copy()->startOfMonth(), $today);

        $lastYearMonthStart = $today->copy()->startOfMonth()->subYear();
        $lastYearSameDay    = $lastYearMonthStart->copy()->day(min($today->day, $lastYearMonthStart->daysInMonth));

        $salesSoFar    = array_sum($thisYearMonthly);
        $lastYearSoFar = array_sum(array_filter($lastYearMonthly, fn ($month) => $month < $monthOfYear, ARRAY_FILTER_USE_KEY))
            + $this->dailySalesTotal($shop, $lastYearMonthStart, $lastYearSameDay);
        $lastYearTotal = array_sum($lastYearMonthly);

        $targets = ShopSalesTarget::where('shop_id', $shop->id)
            ->whereBetween('month', [$yearStart->toDateString(), $yearStart->copy()->endOfYear()->toDateString()])
            ->with('setBy')
            ->get()
            ->keyBy(fn (ShopSalesTarget $target) => $target->month->month);

        $growth = (float) config('marketing.default_sales_target_growth');

        $targetAmount = 0.0;
        foreach (range(1, 12) as $month) {
            $explicitTarget = $targets->get($month);
            $targetAmount   += $explicitTarget
                ? (float) $explicitTarget->target_org_currency
                : round(($lastYearMonthly[$month] ?? 0) * (1 + $growth), 2);
        }
        $targetAmount = $targetAmount > 0 ? round($targetAmount, 2) : null;

        $lastSetTarget = $targets->sortByDesc('updated_at')->first();
        $pipeline      = $this->pipeline($shop);
        $remainingDays = $today->daysInYear - $today->dayOfYear;
        $gap           = $targetAmount === null ? null : max(0, $targetAmount - $salesSoFar - $pipeline['amount']);

        return [
            'month'             => $yearStart->format('Y-m'),
            'month_label'       => __(':year year to date', ['year' => $yearStart->format('Y')]),
            'last_year_label'   => $lastYearStart->format('Y'),
            'currency_code'     => $shop->organisation->currency->code,
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
                'months_set'  => $targets->count(),
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
     * @return array<int, float> month of year => invoiced sales (org currency, partners excluded)
     */
    private function monthlySales(Shop $shop, Carbon $yearStart): array
    {
        return DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->where('shop_time_series.shop_id', $shop->id)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->whereBetween('shop_time_series_records.period', [$yearStart->format('Y-m'), $yearStart->copy()->endOfYear()->format('Y-m')])
            ->pluck('shop_time_series_records.sales_org_currency_external', 'shop_time_series_records.period')
            ->mapWithKeys(fn ($sales, $period) => [(int) substr($period, 5, 2) => (float) $sales])
            ->all();
    }

    private function dailySalesTotal(Shop $shop, Carbon $from, Carbon $to): float
    {
        return (float) DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->where('shop_time_series.shop_id', $shop->id)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereBetween('shop_time_series_records.period', [$from->toDateString(), $to->toDateString()])
            ->sum('shop_time_series_records.sales_org_currency_external');
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
