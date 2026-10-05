<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget;

use App\Actions\Catalogue\Shop\SalesTarget\Concerns\HasOrdersPipeline;
use App\Actions\Catalogue\Shop\SalesTarget\Concerns\HasSalesForecast;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\SalesTargetTip;
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
    use HasSalesForecast;

    public function handle(Shop|Organisation|Group $parent, ?User $user = null, ?Carbon $today = null): array
    {
        $today         = ($today ?? now('UTC'))->copy()->startOfDay();
        $monthStart    = $today->copy()->startOfMonth();
        $lastYearStart = $monthStart->copy()->subYear();

        $salesExpression     = $this->salesExpression($parent);
        $thisYearDailyByShop = $this->dailySalesByShop($this->salesShopIds($parent), $monthStart, $today, $salesExpression);
        $lastYearDailyByShop = $this->dailySalesByShop($this->salesShopIds($parent), $lastYearStart, $lastYearStart->copy()->endOfMonth(), $salesExpression);
        $thisYearDaily       = $this->sumDaily($thisYearDailyByShop);
        $lastYearDaily       = $this->sumDaily($lastYearDailyByShop);
        $forecastByShop      = $this->salesForecastByShop($this->salesShopIds($parent), $today, $parent instanceof Group ? 'grp' : 'org');
        $expectedOf          = fn (array $shopIds) => $this->restOfMonthOfShops($shopIds, $forecastByShop, $thisYearDailyByShop, $lastYearDailyByShop, $today);

        $targetShopIds = $this->targetShopIds($parent);
        $targets       = ShopSalesTarget::whereIn('shop_id', $targetShopIds)->where('month', $monthStart->toDateString())->with($this->targetRelations($parent))->get();
        $targetsByShop = $targets->groupBy('shop_id');
        $growth        = (float) config('marketing.default_sales_target_growth');

        $lastYearByShop = array_map('array_sum', $lastYearDailyByShop);
        $categories     = $parent instanceof Shop ? $this->categoryTargetsOfShop($parent, $monthStart, $today, $targets, $growth, round(($lastYearByShop[$parent->id] ?? 0) * (1 + $growth), 2)) : [];

        $shopTargets = [];
        foreach ($targetShopIds as $shopId) {
            $shopTargets[$shopId] = $this->shopTarget($parent, $shopId, $monthStart, $targetsByShop->get($shopId, collect()), $lastYearByShop[$shopId] ?? 0, $growth);
        }
        $targetAmount = $categories ? array_sum(array_column($categories, 'target')) : array_sum($shopTargets);
        $targetAmount = $targetAmount > 0 ? round($targetAmount, 2) : null;
        $target       = $targets->sortByDesc('updated_at')->first();

        $pipelineByShop = $this->pipelineByShop($parent);
        $pipeline       = $this->sumPipelines($pipelineByShop);

        $children = match (true) {
            $parent instanceof Shop         => $categories ? $this->categoryBlocks($parent, $categories, $targets, $monthStart, $today, $user, $growth, $expectedOf([$parent->id])) : [],
            $parent instanceof Organisation => $this->shopBlocks($parent, $targetShopIds, $thisYearDailyByShop, $lastYearDailyByShop, $shopTargets, $targetsByShop, $pipelineByShop, $monthStart, $today, $growth, $expectedOf),
            default                         => $this->organisationBlocks($parent, $targetShopIds, $thisYearDailyByShop, $lastYearDailyByShop, $shopTargets, $targetsByShop, $pipelineByShop, $monthStart, $today, $growth, $expectedOf),
        };
        $tips = $parent instanceof Shop ? SalesTargetTip::where('shop_id', $parent->id)->where('date', $today->toDateString())->pluck('tip', 'invoice_category_id') : collect();
        $children = array_map(fn (array $child) => [...$child, 'tip' => isset($child['invoice_category_id']) ? $tips->get($child['invoice_category_id']) : null], $children);

        $selectionSetting = match (true) {
            $parent instanceof Shop         => 'shop_target_category_'.$parent->id,
            $parent instanceof Organisation => 'organisation_target_shop_'.$parent->id,
            default                         => 'group_target_organisation',
        };

        return [
            ...$this->periodBlock($monthStart, $today, $thisYearDaily, $lastYearDaily, $targetAmount, $pipeline, $expectedOf($this->salesShopIds($parent))),
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
            'tip'               => $tips->get(''),
            'children'          => $children,
            'children_label'    => match (true) {
                $parent instanceof Shop         => __('Invoice category'),
                $parent instanceof Organisation => __('Shop'),
                default                         => __('Organisation'),
            },
            'selection_setting' => $selectionSetting,
            'selected_child'    => (string) Arr::get($user?->settings ?? [], $selectionSetting, 'all'),
            'selected_period'   => Arr::get($user?->settings ?? [], 'sales_target_period') === 'year' ? 'year' : 'month',
            'can_edit'          => $parent instanceof Shop && $user !== null && UpdateShopSalesTarget::canEdit($user, $parent),
            'update_route'      => $this->updateRoute($parent),
        ];
    }

    /**
     * Each open shop of the organisation in the same shape as the organisation's month.
     *
     * @return list<array<string, mixed>>
     */
    private function shopBlocks(Organisation $organisation, array $shopIds, array $thisYearDailyByShop, array $lastYearDailyByShop, array $shopTargets, Collection $targetsByShop, array $pipelineByShop, Carbon $monthStart, Carbon $today, float $growth, callable $expectedOf): array
    {
        $blocks = Shop::whereIn('id', $shopIds)->get(['id', 'slug', 'name'])->map(fn (Shop $shop) => [
            ...$this->periodBlock($monthStart, $today, $thisYearDailyByShop[$shop->id] ?? [], $lastYearDailyByShop[$shop->id] ?? [], $shopTargets[$shop->id] > 0 ? round($shopTargets[$shop->id], 2) : null, $pipelineByShop[$shop->id] ?? $this->sumPipelines([]), $expectedOf([$shop->id])),
            'key'           => (string) $shop->id,
            'name'          => $shop->name,
            'currency_code' => $organisation->currency->code,
            'target'        => $this->childTarget($shopTargets[$shop->id], $targetsByShop->get($shop->id, collect()), $growth),
            'link'          => ['name' => 'grp.org.shops.show.dashboard.show', 'parameters' => ['organisation' => $organisation->slug, 'shop' => $shop->slug, 'section' => 'target']],
            'can_edit'      => false,
            'update_route'  => null,
        ])->all();

        return $this->biggestTargetFirst($blocks);
    }

    /**
     * Each organisation of the group in the same shape as the group's month, in the group's currency.
     *
     * @return list<array<string, mixed>>
     */
    private function organisationBlocks(Group $group, array $shopIds, array $thisYearDailyByShop, array $lastYearDailyByShop, array $shopTargets, Collection $targetsByShop, array $pipelineByShop, Carbon $monthStart, Carbon $today, float $growth, callable $expectedOf): array
    {
        $salesShopsByOrganisation = Shop::whereIn('id', $this->salesShopIds($group))->pluck('organisation_id', 'id')->groupBy(fn ($organisationId) => $organisationId, true);
        $blocks                   = [];

        foreach (Organisation::whereIn('id', $salesShopsByOrganisation->keys())->get(['id', 'slug', 'name']) as $organisation) {
            $organisationShopIds = $salesShopsByOrganisation->get($organisation->id)->keys()->all();
            $openShopIds         = array_intersect($organisationShopIds, $shopIds);
            $target              = array_sum(array_intersect_key($shopTargets, array_flip($openShopIds)));

            $blocks[] = [
                ...$this->periodBlock(
                    $monthStart,
                    $today,
                    $this->sumDaily(array_intersect_key($thisYearDailyByShop, array_flip($organisationShopIds))),
                    $this->sumDaily(array_intersect_key($lastYearDailyByShop, array_flip($organisationShopIds))),
                    $target > 0 ? round($target, 2) : null,
                    $this->sumPipelines(array_intersect_key($pipelineByShop, array_flip($organisationShopIds))),
                    $expectedOf($organisationShopIds)
                ),
                'key'           => (string) $organisation->id,
                'name'          => $organisation->name,
                'currency_code' => $group->currency->code,
                'target'        => $this->childTarget($target, $targetsByShop->toBase()->only($openShopIds)->flatten(1), $growth, true),
                'link'          => ['name' => 'grp.org.dashboard.show', 'parameters' => ['organisation' => $organisation->slug]],
                'can_edit'      => false,
                'update_route'  => null,
            ];
        }

        return $this->biggestTargetFirst($blocks);
    }

    private function childTarget(float $amount, Collection $targets, float $growth, bool $isSumOfShops = false): array
    {
        $lastSet = $targets->sortByDesc('updated_at')->first();

        return [
            'amount'          => $amount > 0 ? round($amount, 2) : null,
            'is_default'      => $targets->isEmpty(),
            'is_sum_of_shops' => $isSumOfShops,
            'growth'          => $growth,
            'set_by'          => $lastSet?->setBy?->contact_name,
            'set_at'          => $lastSet?->updated_at,
        ];
    }

    private function biggestTargetFirst(array $blocks): array
    {
        usort($blocks, fn (array $a, array $b) => [$b['target']['amount'] ?? 0, $b['sales_so_far']] <=> [$a['target']['amount'] ?? 0, $a['sales_so_far']]);

        return $blocks;
    }

    /**
     * @param  array<int, array<int, float>>  $dailyByShop
     *
     * @return array<int, float>
     */
    private function sumDaily(array $dailyByShop): array
    {
        $daily = [];
        foreach ($dailyByShop as $shopDaily) {
            foreach ($shopDaily as $day => $sales) {
                $daily[$day] = ($daily[$day] ?? 0) + $sales;
            }
        }

        return $daily;
    }

    /**
     * Sales, pipeline and target figures of one month, shared by the shop and each of its categories.
     */
    private function periodBlock(Carbon $monthStart, Carbon $today, array $thisYearDaily, array $lastYearDaily, ?float $targetAmount, array $pipeline, ?array $restOfMonth = null): array
    {
        $daysInMonth   = $monthStart->daysInMonth;
        $dayOfMonth    = $today->day;
        $lastYearStart = $monthStart->copy()->subYear();
        $lastYearDays  = $lastYearStart->daysInMonth;
        $salesSoFar    = array_sum($thisYearDaily);
        $lastYearSoFar = array_sum(array_filter($lastYearDaily, fn ($day) => $day <= $dayOfMonth, ARRAY_FILTER_USE_KEY));
        $remainingDays = $daysInMonth - $dayOfMonth;
        $gap           = $targetAmount === null ? null : max(0, $targetAmount - $salesSoFar - $pipeline['amount']);
        $daysThisWeek  = (int) $today->diffInDays($today->copy()->endOfWeek()->min($monthStart->copy()->endOfMonth())->startOfDay()) + 1;

        return [
            'month'            => $monthStart->format('Y-m'),
            'month_label'      => $monthStart->translatedFormat('F Y'),
            'last_year_label'  => $lastYearStart->translatedFormat('F Y'),
            'day_of_month'     => $dayOfMonth,
            'days_in_month'    => $daysInMonth,
            'sales_so_far'     => round($salesSoFar, 2),
            'last_year_so_far' => round($lastYearSoFar, 2),
            'last_year_total'  => round(array_sum($lastYearDaily), 2),
            'expected'         => round($restOfMonth !== null ? $salesSoFar + array_sum(array_column($restOfMonth, 0)) : $this->expected($salesSoFar, $lastYearSoFar, $lastYearDaily, $dayOfMonth, $daysInMonth), 2),
            'pipeline'         => $pipeline,
            'gap'              => $gap === null ? null : round($gap, 2),
            'needed_per_day'   => $gap === null ? null : round($remainingDays > 0 ? $gap / $remainingDays : $gap, 2),
            'needed_this_week' => $gap === null ? null : round($gap * $daysThisWeek / ($remainingDays + 1), 2),
            'remaining_days'   => $remainingDays,
            'chart'            => [
                'days'      => range(1, max($daysInMonth, $lastYearDays)),
                'this_year' => $this->cumulative($thisYearDaily, $dayOfMonth),
                'last_year' => $this->cumulative($lastYearDaily, $lastYearDays),
                'forecast'  => $restOfMonth !== null ? $this->forecastLine($salesSoFar, $dayOfMonth, $restOfMonth, max($daysInMonth, $lastYearDays), self::MONTH_BAND_FACTOR) : null,
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
    private function categoryBlocks(Shop $shop, array $categories, Collection $targets, Carbon $monthStart, Carbon $today, ?User $user, float $growth, ?array $shopRestOfMonth): array
    {
        $pipelines = $this->pipelineByCategory($shop);
        foreach (array_diff(array_keys($pipelines), array_map(fn (array $category) => $category['invoice_category_id'] ?? 0, $categories)) as $categoryKey) {
            $categories[] = ['invoice_category_id' => $categoryKey ?: null, 'daily' => [], 'last_year_daily' => [], 'sales' => 0.0, 'last_year' => 0.0, 'target' => 0.0, 'is_set' => false];
        }

        $invoiceCategories = InvoiceCategory::whereIn('id', array_filter(array_column($categories, 'invoice_category_id')))->get(['id', 'name', 'settings']);
        $names             = $invoiceCategories->pluck('name', 'id');
        $ofOtherShopIds    = $invoiceCategories->filter(fn (InvoiceCategory $invoiceCategory) => !empty($invoiceCategory->settings['shop_ids']) && !in_array($shop->id, $invoiceCategory->settings['shop_ids']))->pluck('id')->all();
        $emptyPipeline     = ['amount' => 0.0, 'orders' => 0, 'submitted_amount' => 0.0, 'in_warehouse_amount' => 0.0];
        $canEdit           = $user !== null && UpdateShopSalesTarget::canEdit($user, $shop);

        usort($categories, fn (array $a, array $b) => [$b['target'], $b['sales']] <=> [$a['target'], $a['sales']]);
        $restByCategory = $this->shareForecastAcrossCategories($categories, $shopRestOfMonth, $today);

        $blocks = array_map(function (array $category, int $position) use ($names, $pipelines, $emptyPipeline, $canEdit, $targets, $shop, $monthStart, $today, $growth, $restByCategory) {
            $categoryId = $category['invoice_category_id'];
            $setTarget  = $categoryId ? $targets->firstWhere('invoice_category_id', $categoryId) : null;

            return [
                ...$this->periodBlock($monthStart, $today, $category['daily'], $category['last_year_daily'], $category['target'] > 0 ? $category['target'] : null, $pipelines[$categoryId ?? 0] ?? $emptyPipeline, $restByCategory[$position] ?? null),
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
        }, $categories, array_keys($categories));

        return array_values(array_filter($blocks, fn (array $block) => !in_array($block['invoice_category_id'], $ofOtherShopIds)));
    }

    /**
     * The shop's forecast for the rest of the month split across its invoice categories by what
     * each one's last-year pattern expects from it, so the categories add up to the shop.
     *
     * @param  array<int, array{0: float, 1: float}>|null  $shopRestOfMonth
     *
     * @return array<int, array<int, array{0: float, 1: float}>> category position => day of month => [expected, variance]
     */
    private function shareForecastAcrossCategories(array $categories, ?array $shopRestOfMonth, Carbon $today): array
    {
        if ($shopRestOfMonth === null) {
            return [];
        }

        $salesSoFar = $patternRest = $lastYearRest = [];
        foreach ($categories as $position => $category) {
            $salesSoFar[$position]   = array_sum($category['daily']);
            $lastYearSoFar           = array_sum(array_filter($category['last_year_daily'], fn ($day) => $day <= $today->day, ARRAY_FILTER_USE_KEY));
            $lastYearRest[$position] = max(0.0, array_sum($category['last_year_daily']) - $lastYearSoFar);
            $patternRest[$position]  = max(0.0, $this->expected($salesSoFar[$position], $lastYearSoFar, $category['last_year_daily'], $today->day, $today->daysInMonth) - $salesSoFar[$position]);
        }

        $weights = collect([$patternRest, $lastYearRest, array_map(fn (float $sales) => max(0.0, $sales), $salesSoFar)])
            ->first(fn (array $candidate) => array_sum($candidate) > 0, array_fill_keys(array_keys($categories), 1.0));
        $total   = array_sum($weights);

        return array_map(
            fn (float $weight) => array_map(fn (array $day) => [$day[0] * $weight / $total, $day[1] * ($weight / $total) ** 2], $shopRestOfMonth),
            $weights
        );
    }

    /**
     * @return array<int, array<int, float>> shop id => day of month => invoiced sales (partners included)
     */
    private function dailySalesByShop(array $shopIds, Carbon $from, Carbon $to, string $salesExpression): array
    {
        $byShop = [];
        $rows   = $this->dailyRecords($shopIds, $from, $to)
            ->groupBy('shop_time_series.shop_id', 'shop_time_series_records.period')
            ->selectRaw("shop_time_series.shop_id, shop_time_series_records.period, sum($salesExpression) as sales")
            ->get();

        foreach ($rows as $row) {
            $byShop[$row->shop_id][(int) substr($row->period, 8, 2)] = (float) $row->sales;
        }

        return $byShop;
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
     * The days after today of a group of shops: each shop's forecast, or its own last-year pattern
     * (its run rate without one) when it has none. Null when none of them has a forecast, so the
     * block keeps the last-year pattern of its total.
     *
     * @return array<int, array{0: float, 1: float}>|null day of month => [expected, variance]
     */
    private function restOfMonthOfShops(array $shopIds, array $forecastByShop, array $thisYearDailyByShop, array $lastYearDailyByShop, Carbon $today): ?array
    {
        if (!array_intersect_key($forecastByShop, array_flip($shopIds))) {
            return null;
        }

        $month = $today->format('Y-m');
        $rest  = $today->day < $today->daysInMonth ? array_fill_keys(range($today->day + 1, $today->daysInMonth), [0.0, 0.0]) : [];
        foreach ($shopIds as $shopId) {
            if (isset($forecastByShop[$shopId])) {
                foreach ($forecastByShop[$shopId] as $date => [$expected, $variance]) {
                    if (str_starts_with($date, $month)) {
                        $day        = (int) substr($date, 8, 2);
                        $rest[$day] = [$rest[$day][0] + $expected, $rest[$day][1] + $variance];
                    }
                }
                continue;
            }

            $salesSoFar    = array_sum($thisYearDailyByShop[$shopId] ?? []);
            $lastYearDaily = $lastYearDailyByShop[$shopId] ?? [];
            $lastYearSoFar = array_sum(array_filter($lastYearDaily, fn ($day) => $day <= $today->day, ARRAY_FILTER_USE_KEY));
            foreach (array_keys($rest) as $day) {
                $rest[$day][0] += $lastYearSoFar > 0 ? ($lastYearDaily[$day] ?? 0) * $salesSoFar / $lastYearSoFar : $salesSoFar / $today->day;
            }
        }

        return $rest;
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
