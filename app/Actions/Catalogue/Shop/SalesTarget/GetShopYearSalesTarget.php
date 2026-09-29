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
 * Year-to-date sales against target: the same shape as GetShopMonthSalesTarget, but summed
 * month by month across the calendar year instead of day by day across the month.
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

        $thisYearMonthly = $this->monthlySales($shop, $yearStart, $yearStart->copy()->endOfYear());
        $lastYearMonthly = $this->monthlySales($shop, $lastYearStart, $lastYearStart->copy()->endOfYear());

        $salesSoFar         = array_sum(array_filter($thisYearMonthly, fn ($month) => $month <= $monthOfYear, ARRAY_FILTER_USE_KEY));
        $lastYearSoFar      = array_sum(array_filter($lastYearMonthly, fn ($month) => $month <= $monthOfYear, ARRAY_FILTER_USE_KEY));
        $lastYearYearTotal  = array_sum($lastYearMonthly);

        $targets = ShopSalesTarget::where('shop_id', $shop->id)
            ->whereBetween('month', [$yearStart->toDateString(), $yearStart->copy()->endOfYear()->toDateString()])
            ->with('setBy')
            ->get()
            ->keyBy(fn (ShopSalesTarget $target) => $target->month->month);

        $growth = (float) config('marketing.default_sales_target_growth');

        $targetAmount = 0.0;
        foreach (range(1, 12) as $month) {
            $set = $targets->get($month);
            if ($set) {
                $targetAmount += (float) $set->target_org_currency;
            } else {
                $lastYearMonth = $lastYearMonthly[$month] ?? 0;
                $targetAmount += $lastYearMonth > 0 ? round($lastYearMonth * (1 + $growth), 2) : 0;
            }
        }

        $lastOverride  = $targets->sortByDesc('updated_at')->first();
        $remainingDays = $today->diffInDays($yearStart->copy()->endOfYear());
        $gap           = $targetAmount > 0 ? max(0, $targetAmount - $salesSoFar - $this->pipeline($shop)['amount']) : null;
        $pipeline      = $this->pipeline($shop);

        return [
            'month'             => $yearStart->format('Y-m'),
            'month_label'       => __(':year year to date', ['year' => $yearStart->format('Y')]),
            'last_year_label'   => $lastYearStart->format('Y'),
            'currency_code'     => $shop->organisation->currency->code,
            'day_of_month'      => $monthOfYear,
            'days_in_month'     => 12,
            'sales_so_far'      => round($salesSoFar, 2),
            'last_year_so_far'  => round($lastYearSoFar, 2),
            'last_year_total'   => round($lastYearYearTotal, 2),
            'expected'          => round($this->expected($salesSoFar, $lastYearSoFar, $lastYearMonthly, $monthOfYear), 2),
            'pipeline'          => $pipeline,
            'target'            => [
                'amount'      => $targetAmount > 0 ? round($targetAmount, 2) : null,
                'is_default'  => $targets->isEmpty(),
                'growth'      => $growth,
                'set_by'      => $lastOverride?->setBy?->contact_name,
                'set_at'      => $lastOverride?->updated_at,
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
     * @return array<int, float> month of year => invoiced sales (org currency, partners excluded)
     */
    private function monthlySales(Shop $shop, Carbon $from, Carbon $to): array
    {
        return DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->where('shop_time_series.shop_id', $shop->id)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->whereBetween('shop_time_series_records.period', [$from->toDateString(), $to->toDateString()])
            ->pluck('shop_time_series_records.sales_org_currency_external', 'shop_time_series_records.period')
            ->mapWithKeys(fn ($sales, $period) => [(int) substr($period, 5, 2) => (float) $sales])
            ->all();
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
     * Last year's remaining months, scaled by how this year is running against the same
     * months last year. With no history for those months it falls back to the current run rate.
     */
    private function expected(float $salesSoFar, float $lastYearSoFar, array $lastYearMonthly, int $monthOfYear): float
    {
        if ($lastYearSoFar <= 0) {
            return $salesSoFar / $monthOfYear * 12;
        }

        $lastYearRest = array_sum(array_filter($lastYearMonthly, fn ($month) => $month > $monthOfYear, ARRAY_FILTER_USE_KEY));

        return $salesSoFar + $lastYearRest * ($salesSoFar / $lastYearSoFar);
    }
}
