<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget;

use App\Actions\Catalogue\Shop\SalesTarget\Concerns\HasOrdersPipeline;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopSalesTarget;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The month's sales against target: what staff bonuses are paid on. Invoiced sales
 * (partners included) so far this month against the same days last year, plus the
 * orders already in the pipeline that will invoice soon. A shop with more than one
 * invoice category also gets each category's sales and target.
 */
class GetShopMonthSalesTarget
{
    use AsObject;
    use HasOrdersPipeline;

    public function handle(Shop|Organisation|Group $parent, ?User $user = null, ?Carbon $today = null): array
    {
        $today         = ($today ?? now('UTC'))->copy()->startOfDay();
        $monthStart    = $today->copy()->startOfMonth();
        $lastYearStart = $monthStart->copy()->subYear();

        $salesExpression = $this->salesExpression($parent);
        $thisYearDaily   = $this->dailySales($this->salesShopIds($parent), $monthStart, $today, $salesExpression);
        $lastYearDaily   = $this->dailySales($this->salesShopIds($parent), $lastYearStart, $lastYearStart->copy()->endOfMonth(), $salesExpression);

        $targetShopIds = $this->targetShopIds($parent);
        $targets       = ShopSalesTarget::whereIn('shop_id', $targetShopIds)->where('month', $monthStart->toDateString())->with($this->targetRelations($parent))->get();
        $targetsByShop = $targets->groupBy('shop_id');
        $growth        = (float) config('marketing.default_sales_target_growth');

        $lastYearByShop = $this->salesByShop($targetShopIds, $lastYearStart, $lastYearStart->copy()->endOfMonth(), $salesExpression);
        $categories     = $parent instanceof Shop ? $this->categoryTargetsOfShop($parent, $monthStart, $today, $targets, $growth, round(($lastYearByShop[$parent->id] ?? 0) * (1 + $growth), 2)) : [];

        $targetAmount = 0.0;
        if ($categories) {
            $targetAmount = array_sum(array_column($categories, 'target'));
        } else {
            foreach ($targetShopIds as $shopId) {
                $targetAmount += $this->shopTarget($parent, $shopId, $monthStart, $targetsByShop->get($shopId, collect()), $lastYearByShop[$shopId] ?? 0, $growth);
            }
        }
        $targetAmount = $targetAmount > 0 ? round($targetAmount, 2) : null;
        $target       = $targets->sortByDesc('updated_at')->first();

        $pipeline = $this->pipeline($parent);

        return [
            ...$this->periodBlock($monthStart, $today, $thisYearDaily, $lastYearDaily, $targetAmount, $pipeline),
            'currency_code'     => $this->currencyCode($parent),
            'target'            => [
                'amount'               => $targetAmount,
                'is_default'           => $targets->isEmpty(),
                'is_sum_of_shops'      => !$parent instanceof Shop,
                'is_sum_of_categories' => (bool) $categories,
                'growth'               => $growth,
                'set_by'               => $target?->setBy?->contact_name,
                'set_at'               => $target?->updated_at,
            ],
            'categories'        => $parent instanceof Shop && $categories ? $this->categoryBlocks($parent, $categories, $targets, $monthStart, $today, $user, $growth) : [],
            'selected_category' => $parent instanceof Shop ? (string) Arr::get($user?->settings ?? [], 'shop_target_category_'.$parent->id, 'all') : 'all',
            'can_edit'          => $parent instanceof Shop && $user !== null && UpdateShopSalesTarget::canEdit($user, $parent),
            'update_route'      => $this->updateRoute($parent),
        ];
    }

    /**
     * Sales, pipeline and target figures of one month, shared by the shop and each of its categories.
     */
    private function periodBlock(Carbon $monthStart, Carbon $today, array $thisYearDaily, array $lastYearDaily, ?float $targetAmount, array $pipeline): array
    {
        $daysInMonth   = $monthStart->daysInMonth;
        $dayOfMonth    = $today->day;
        $lastYearStart = $monthStart->copy()->subYear();
        $lastYearDays  = $lastYearStart->daysInMonth;
        $salesSoFar    = array_sum($thisYearDaily);
        $lastYearSoFar = array_sum(array_filter($lastYearDaily, fn ($day) => $day <= $dayOfMonth, ARRAY_FILTER_USE_KEY));
        $remainingDays = $daysInMonth - $dayOfMonth;
        $gap           = $targetAmount === null ? null : max(0, $targetAmount - $salesSoFar - $pipeline['amount']);

        return [
            'month'            => $monthStart->format('Y-m'),
            'month_label'      => $monthStart->translatedFormat('F Y'),
            'last_year_label'  => $lastYearStart->translatedFormat('F Y'),
            'day_of_month'     => $dayOfMonth,
            'days_in_month'    => $daysInMonth,
            'sales_so_far'     => round($salesSoFar, 2),
            'last_year_so_far' => round($lastYearSoFar, 2),
            'last_year_total'  => round(array_sum($lastYearDaily), 2),
            'expected'         => round($this->expected($salesSoFar, $lastYearSoFar, $lastYearDaily, $dayOfMonth, $daysInMonth), 2),
            'pipeline'         => $pipeline,
            'gap'              => $gap === null ? null : round($gap, 2),
            'needed_per_day'   => $gap === null ? null : round($remainingDays > 0 ? $gap / $remainingDays : $gap, 2),
            'remaining_days'   => $remainingDays,
            'chart'            => [
                'days'      => range(1, max($daysInMonth, $lastYearDays)),
                'this_year' => $this->cumulative($thisYearDaily, $dayOfMonth),
                'last_year' => $this->cumulative($lastYearDaily, $lastYearDays),
            ],
        ];
    }

    private function updateRoute(Shop|Organisation|Group $parent): ?array
    {
        return $parent instanceof Shop ? [
            'name'       => 'grp.models.org.shop.sales_target.update',
            'parameters' => ['organisation' => $parent->organisation_id, 'shop' => $parent->id],
            'method'     => 'patch',
        ] : null;
    }

    /**
     * A shop selling under more than one invoice category; one category is the shop.
     */
    private function categoryTargetsOfShop(Shop $shop, Carbon $monthStart, Carbon $today, Collection $targets, float $growth, float $defaultTarget): array
    {
        $categories = $this->categoryTargets($shop->id, $monthStart, $today, $targets, $growth, $defaultTarget);

        return count($categories) < 2 ? [] : $categories;
    }

    /**
     * Each category in the same shape as the shop's month, biggest target first.
     *
     * @return list<array<string, mixed>>
     */
    private function categoryBlocks(Shop $shop, array $categories, Collection $targets, Carbon $monthStart, Carbon $today, ?User $user, float $growth): array
    {
        $pipelines = $this->pipelineByCategory($shop);
        foreach (array_diff(array_keys($pipelines), array_map(fn (array $category) => $category['invoice_category_id'] ?? 0, $categories)) as $categoryKey) {
            $categories[] = ['invoice_category_id' => $categoryKey ?: null, 'daily' => [], 'last_year_daily' => [], 'sales' => 0.0, 'last_year' => 0.0, 'target' => 0.0, 'is_set' => false];
        }

        $names         = InvoiceCategory::whereIn('id', array_filter(array_column($categories, 'invoice_category_id')))->pluck('name', 'id');
        $emptyPipeline = ['amount' => 0.0, 'orders' => 0, 'submitted_amount' => 0.0, 'in_warehouse_amount' => 0.0];
        $canEdit       = $user !== null && UpdateShopSalesTarget::canEdit($user, $shop);

        usort($categories, fn (array $a, array $b) => [$b['target'], $b['sales']] <=> [$a['target'], $a['sales']]);

        return array_map(function (array $category) use ($names, $pipelines, $emptyPipeline, $canEdit, $targets, $shop, $monthStart, $today, $growth) {
            $categoryId = $category['invoice_category_id'];
            $setTarget  = $categoryId ? $targets->firstWhere('invoice_category_id', $categoryId) : null;

            return [
                ...$this->periodBlock($monthStart, $today, $category['daily'], $category['last_year_daily'], $category['target'] > 0 ? $category['target'] : null, $pipelines[$categoryId ?? 0] ?? $emptyPipeline),
                'key'                 => (string) ($categoryId ?? 'none'),
                'invoice_category_id' => $categoryId,
                'name'                => $categoryId ? $names->get($categoryId, '') : __('No category'),
                'currency_code'       => $shop->organisation->currency->code,
                'target'              => [
                    'amount'     => $category['target'] > 0 ? $category['target'] : null,
                    'is_default' => !$category['is_set'],
                    'is_share'   => !$category['is_set'],
                    'growth'     => $growth,
                    'set_by'     => $setTarget?->setBy?->contact_name,
                    'set_at'     => $setTarget?->updated_at,
                ],
                'can_edit'            => $canEdit && $categoryId !== null,
                'update_route'        => $this->updateRoute($shop),
            ];
        }, $categories);
    }

    /**
     * @return array<int, float> day of month => invoiced sales (org currency, partners included)
     */
    private function dailySales(array $shopIds, Carbon $from, Carbon $to, string $salesExpression): array
    {
        return $this->dailyRecords($shopIds, $from, $to)
            ->groupBy('shop_time_series_records.period')
            ->selectRaw("shop_time_series_records.period, sum($salesExpression) as sales")
            ->pluck('sales', 'period')
            ->mapWithKeys(fn ($sales, $period) => [(int) substr($period, 8, 2) => (float) $sales])
            ->all();
    }

    /**
     * @return array<int, float> shop id => invoiced sales (org currency, partners included)
     */
    private function salesByShop(array $shopIds, Carbon $from, Carbon $to, string $salesExpression): array
    {
        return $this->dailyRecords($shopIds, $from, $to)
            ->groupBy('shop_time_series.shop_id')
            ->selectRaw("shop_time_series.shop_id, sum($salesExpression) as sales")
            ->pluck('sales', 'shop_id')
            ->map(fn ($sales) => (float) $sales)
            ->all();
    }

    private function dailyRecords(array $shopIds, Carbon $from, Carbon $to): Builder
    {
        return DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->whereIn('shop_time_series.shop_id', $shopIds)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereBetween('shop_time_series_records.period', [$from->toDateString(), $to->toDateString()]);
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
