<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
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
 * The month's sales against target: what staff bonuses are paid on. Invoiced sales
 * (partners excluded) so far this month against the same days last year, plus the
 * orders already in the pipeline that will invoice soon.
 */
class GetShopMonthSalesTarget
{
    use AsObject;
    use HasOrdersPipeline;

    public function handle(Shop $shop, ?User $user = null, ?Carbon $today = null): array
    {
        $today           = ($today ?? now('UTC'))->copy()->startOfDay();
        $monthStart      = $today->copy()->startOfMonth();
        $daysInMonth     = $monthStart->daysInMonth;
        $dayOfMonth      = $today->day;
        $lastYearStart   = $monthStart->copy()->subYear();
        $lastYearDays    = $lastYearStart->daysInMonth;

        $thisYearDaily = $this->dailySales($shop, $monthStart, $today);
        $lastYearDaily = $this->dailySales($shop, $lastYearStart, $lastYearStart->copy()->endOfMonth());

        $salesSoFar         = array_sum($thisYearDaily);
        $lastYearSoFar      = array_sum(array_filter($lastYearDaily, fn ($day) => $day <= $dayOfMonth, ARRAY_FILTER_USE_KEY));
        $lastYearMonthTotal = array_sum($lastYearDaily);

        $target = ShopSalesTarget::where('shop_id', $shop->id)->where('month', $monthStart->toDateString())->with('setBy')->first();
        $growth = (float) config('marketing.default_sales_target_growth');

        $targetAmount = $target
            ? (float) $target->target_org_currency
            : ($lastYearMonthTotal > 0 ? round($lastYearMonthTotal * (1 + $growth), 2) : null);

        $pipeline = $this->pipeline($shop);

        $remainingDays = $daysInMonth - $dayOfMonth;
        $gap           = $targetAmount === null ? null : max(0, $targetAmount - $salesSoFar - $pipeline['amount']);

        return [
            'month'             => $monthStart->format('Y-m'),
            'month_label'       => $monthStart->translatedFormat('F Y'),
            'last_year_label'   => $lastYearStart->translatedFormat('F Y'),
            'currency_code'     => $shop->organisation->currency->code,
            'day_of_month'      => $dayOfMonth,
            'days_in_month'     => $daysInMonth,
            'sales_so_far'      => round($salesSoFar, 2),
            'last_year_so_far'  => round($lastYearSoFar, 2),
            'last_year_total'   => round($lastYearMonthTotal, 2),
            'expected'          => round($this->expected($salesSoFar, $lastYearSoFar, $lastYearDaily, $dayOfMonth, $daysInMonth), 2),
            'pipeline'          => $pipeline,
            'target'            => [
                'amount'      => $targetAmount,
                'is_default'  => $target === null,
                'growth'      => $growth,
                'set_by'      => $target?->setBy?->contact_name,
                'set_at'      => $target?->updated_at,
            ],
            'gap'               => $gap === null ? null : round($gap, 2),
            'needed_per_day'    => $gap === null ? null : round($remainingDays > 0 ? $gap / $remainingDays : $gap, 2),
            'remaining_days'    => $remainingDays,
            'chart'             => [
                'days'      => range(1, max($daysInMonth, $lastYearDays)),
                'this_year' => $this->cumulative($thisYearDaily, $dayOfMonth),
                'last_year' => $this->cumulative($lastYearDaily, $lastYearDays),
            ],
            'can_edit'          => $user !== null && UpdateShopSalesTarget::canEdit($user, $shop),
            'update_route'      => [
                'name'       => 'grp.models.org.shop.sales_target.update',
                'parameters' => ['organisation' => $shop->organisation_id, 'shop' => $shop->id],
                'method'     => 'patch',
            ],
        ];
    }

    /**
     * @return array<int, float> day of month => invoiced sales (org currency, partners excluded)
     */
    private function dailySales(Shop $shop, Carbon $from, Carbon $to): array
    {
        return DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->where('shop_time_series.shop_id', $shop->id)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereBetween('shop_time_series_records.period', [$from->toDateString(), $to->toDateString()])
            ->pluck('shop_time_series_records.sales_org_currency_external', 'shop_time_series_records.period')
            ->mapWithKeys(fn ($sales, $period) => [(int) substr($period, 8, 2) => (float) $sales])
            ->all();
    }

    private function cumulative(array $daily, int $untilDay): array
    {
        $runningTotal = 0;
        $series       = [];
        for ($day = 1; $day <= $untilDay; $day++) {
            $runningTotal += $daily[$day] ?? 0;
            $series[]     = round($runningTotal, 2);
        }

        return $series;
    }

    /**
     * Last year's remaining days, scaled by how this month is running against the same days last
     * year. With no history for those days it falls back to the current daily run rate.
     */
    private function expected(float $salesSoFar, float $lastYearSoFar, array $lastYearDaily, int $dayOfMonth, int $daysInMonth): float
    {
        if ($lastYearSoFar <= 0) {
            return $salesSoFar / $dayOfMonth * $daysInMonth;
        }

        $lastYearRest = array_sum(array_filter($lastYearDaily, fn ($day) => $day > $dayOfMonth, ARRAY_FILTER_USE_KEY));

        return $salesSoFar + $lastYearRest * ($salesSoFar / $lastYearSoFar);
    }
}
