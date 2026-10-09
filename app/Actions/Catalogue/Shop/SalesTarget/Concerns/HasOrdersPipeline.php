<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget\Concerns;

use App\Actions\Accounting\Invoice\CategoriseInvoice;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopSalesTarget;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasOrdersPipeline
{
    public const array PIPELINE_STATES = [
        OrderStateEnum::SUBMITTED,
        OrderStateEnum::IN_WAREHOUSE,
        OrderStateEnum::HANDLING,
        OrderStateEnum::HANDLING_BLOCKED,
        OrderStateEnum::PICKED,
        OrderStateEnum::PACKING,
        OrderStateEnum::PACKED,
    ];

    /**
     * @return array{amount: float, orders: int, submitted_amount: float, in_warehouse_amount: float}
     */
    private function pipeline(Shop|Organisation|Group $parent): array
    {
        return $this->sumPipelines($this->pipelineByShop($parent));
    }

    /**
     * @return array<int, array{amount: float, orders: int, submitted_amount: float, in_warehouse_amount: float}>
     */
    private function pipelineByShop(Shop|Organisation|Group $parent): array
    {
        $rows = DB::table('orders')
            ->whereIn('orders.shop_id', $this->salesShopIds($parent))
            ->whereIn('orders.state', array_map(fn (OrderStateEnum $state) => $state->value, self::PIPELINE_STATES))
            ->whereNull('orders.deleted_at')
            ->selectRaw('orders.shop_id, orders.state = ? as is_submitted, count(*) as orders, coalesce(sum(orders.'.($parent instanceof Group ? 'grp_net_amount' : 'org_net_amount').'), 0) as amount', [OrderStateEnum::SUBMITTED->value])
            ->groupByRaw('1, 2')
            ->get();

        $pipelines = [];
        foreach ($rows->groupBy('shop_id') as $shopId => $shopRows) {
            $submitted   = (float) ($shopRows->firstWhere('is_submitted', true)->amount ?? 0);
            $inWarehouse = (float) ($shopRows->firstWhere('is_submitted', false)->amount ?? 0);

            $pipelines[$shopId] = [
                'amount'              => round($submitted + $inWarehouse, 2),
                'orders'              => (int) $shopRows->sum('orders'),
                'submitted_amount'    => round($submitted, 2),
                'in_warehouse_amount' => round($inWarehouse, 2),
            ];
        }

        return $pipelines;
    }

    /**
     * @param  array<array{amount: float, orders: int, submitted_amount: float, in_warehouse_amount: float}>  $pipelines
     *
     * @return array{amount: float, orders: int, submitted_amount: float, in_warehouse_amount: float}
     */
    private function sumPipelines(array $pipelines): array
    {
        return [
            'amount'              => round(array_sum(array_column($pipelines, 'amount')), 2),
            'orders'              => (int) array_sum(array_column($pipelines, 'orders')),
            'submitted_amount'    => round(array_sum(array_column($pipelines, 'submitted_amount')), 2),
            'in_warehouse_amount' => round(array_sum(array_column($pipelines, 'in_warehouse_amount')), 2),
        ];
    }

    /**
     * Invoices (refunds left out) issued between the two days, partners included as in the sales figures.
     *
     * @return array<int, int> shop id => invoices
     */
    private function invoiceCountsByShop(array $shopIds, Carbon $from, Carbon $to): array
    {
        return DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->whereIn('shop_time_series.shop_id', $shopIds)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->whereBetween('shop_time_series_records.period', [$from->toDateString(), $to->toDateString()])
            ->groupBy('shop_time_series.shop_id')
            ->selectRaw('shop_time_series.shop_id, sum(coalesce(shop_time_series_records.invoices, 0) + coalesce(shop_time_series_records.invoices_internal, 0)) as invoices')
            ->pluck('invoices', 'shop_id')
            ->map(fn ($invoices) => (int) $invoices)
            ->all();
    }

    /**
     * @return array<int, int> invoice category id (0 for none) => invoices
     */
    private function invoiceCountsByCategory(Shop $shop, Carbon $from, Carbon $to): array
    {
        return DB::table('invoices')
            ->where('shop_id', $shop->id)
            ->where('type', InvoiceTypeEnum::INVOICE->value)
            ->where('in_process', false)
            ->whereNull('deleted_at')
            ->whereBetween('date', [$from->toDateString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->groupBy('invoice_category_id')
            ->selectRaw('coalesce(invoice_category_id, 0) as category_key, count(*) as invoices')
            ->pluck('invoices', 'category_key')
            ->map(fn ($invoices) => (int) $invoices)
            ->all();
    }

    /**
     * @param array<int, int> $invoiceCountsByShop
     */
    private function sumInvoiceCounts(array $invoiceCountsByShop, ?array $shopIds = null): int
    {
        return (int) array_sum($shopIds === null ? $invoiceCountsByShop : array_intersect_key($invoiceCountsByShop, array_flip($shopIds)));
    }

    /**
     * @return list<int>
     */
    private function salesShopIds(Shop|Organisation|Group $parent): array
    {
        return $parent instanceof Shop ? [$parent->id] : $parent->shops()->pluck('id')->all();
    }

    /**
     * Closed shops still count in sales history but no longer carry a target.
     *
     * @return list<int>
     */
    private function targetShopIds(Shop|Organisation|Group $parent): array
    {
        return $parent instanceof Shop ? [$parent->id] : $parent->shops()->where('state', '!=', ShopStateEnum::CLOSED)->pluck('id')->all();
    }

    /**
     * Sales to partner organisations count towards the target like any other customer's.
     */
    private function salesExpression(Shop|Organisation|Group $parent): string
    {
        $currency = $parent instanceof Group ? 'grp' : 'org';

        return "shop_time_series_records.sales_{$currency}_currency_external + coalesce(shop_time_series_records.sales_{$currency}_currency_internal, 0)";
    }

    /**
     * @return list<string>
     */
    private function targetRelations(Shop|Organisation|Group $parent): array
    {
        return $parent instanceof Group ? ['setBy', 'shop.organisation.currency'] : ['setBy'];
    }

    private function currencyCode(Shop|Organisation|Group $parent): string
    {
        return ($parent instanceof Shop ? $parent->organisation : $parent)->currency->code;
    }

    /**
     * Targets are set in the organisation's currency; the group adds them up in its own at
     * today's rate. Without a rate the shop keeps its default target instead of a wrong one.
     */
    private function targetInParentCurrency(ShopSalesTarget $target, Shop|Organisation|Group $parent): ?float
    {
        return $this->orgAmountInParentCurrency((float) $target->target_org_currency, $target, $parent);
    }

    private function orgAmountInParentCurrency(float $amount, ShopSalesTarget $target, Shop|Organisation|Group $parent): ?float
    {
        if (!$parent instanceof Group) {
            return $amount;
        }

        $rate = GetCurrencyExchange::run($target->shop->organisation->currency, $parent->currency);

        return $rate ? $amount * $rate : null;
    }

    /**
     * One shop's target for one month, in the parent's currency. Once any of its invoice categories
     * has a target the shop targets the sum of its categories.
     *
     * @param  Collection<int, ShopSalesTarget>  $targets  the shop's rows for that month
     */
    private function shopTarget(Shop|Organisation|Group $parent, int $shopId, Carbon $monthStart, Collection $targets, float $lastYearSales, float $growth): float
    {
        $shopTarget = $targets->first(fn (ShopSalesTarget $target) => $target->invoice_category_id === null);

        if ($targets->whereNotNull('invoice_category_id')->isEmpty()) {
            $explicitTarget = $shopTarget ? $this->targetInParentCurrency($shopTarget, $parent) : null;

            return $explicitTarget ?? round($lastYearSales * (1 + $growth), 2);
        }

        $defaultTarget    = $parent instanceof Group ? null : round($lastYearSales * (1 + $growth), 2);
        $categoriesTarget = array_sum(array_column($this->categoryTargets($shopId, $monthStart, $monthStart->copy()->endOfMonth(), $targets, $growth, $defaultTarget), 'target'));

        return $this->orgAmountInParentCurrency($categoriesTarget, $targets->first(), $parent) ?? round($lastYearSales * (1 + $growth), 2);
    }

    /**
     * Each invoice category of a shop with its sales and target, in the organisation's currency. A
     * category without its own target gets the shop's target (set by management, otherwise last
     * year plus growth) split by its share of the same month last year, so until a category is
     * set the categories add up to the shop's target.
     *
     * @param  Collection<int, ShopSalesTarget>  $targets  the shop's rows for that month
     * @param  float|null  $defaultTarget  the shop's default target in the organisation's currency
     *
     * @return array<int, array{invoice_category_id: int|null, daily: array<int, float>, last_year_daily: array<int, float>, sales: float, last_year: float, target: float, is_set: bool}>
     */
    private function categoryTargets(int $shopId, Carbon $monthStart, Carbon $until, Collection $targets, float $growth, ?float $defaultTarget = null): array
    {
        $sales           = $this->categorySales($shopId, $monthStart, $until);
        $categoryTargets = $targets->whereNotNull('invoice_category_id')->keyBy('invoice_category_id');

        foreach ($categoryTargets->keys() as $categoryId) {
            $sales[$categoryId] ??= ['daily' => [], 'last_year_daily' => []];
        }

        $lastYearTotal = array_sum(array_map(fn (array $categorySales) => array_sum($categorySales['last_year_daily']), $sales));
        $shopTarget    = $targets->first(fn (ShopSalesTarget $target) => $target->invoice_category_id === null);
        $baseTarget    = $shopTarget ? (float) $shopTarget->target_org_currency : ($defaultTarget ?? $lastYearTotal * (1 + $growth));

        $categories = [];
        foreach ($sales as $categoryKey => $categorySales) {
            $categoryId     = $categoryKey ?: null;
            $explicitTarget = $categoryId ? $categoryTargets->get($categoryId) : null;
            $lastYear       = array_sum($categorySales['last_year_daily']);
            $categories[]   = [
                'invoice_category_id' => $categoryId,
                'daily'               => $categorySales['daily'],
                'last_year_daily'     => $categorySales['last_year_daily'],
                'sales'               => round(array_sum($categorySales['daily']), 2),
                'last_year'           => round($lastYear, 2),
                'target'              => round($explicitTarget ? (float) $explicitTarget->target_org_currency : ($lastYearTotal > 0 ? $baseTarget * $lastYear / $lastYearTotal : 0), 2),
                'is_set'              => $explicitTarget !== null,
            ];
        }

        return $categories;
    }

    /**
     * Invoiced sales per invoice category (0 for invoices without one) and day of month, from the
     * start of the month until the given day, and for the whole same month last year.
     *
     * @return array<int, array{daily: array<int, float>, last_year_daily: array<int, float>}>
     */
    private function categorySales(int $shopId, Carbon $monthStart, Carbon $until): array
    {
        $lastYearStart = $monthStart->copy()->subYear();
        $lastYearEnd   = $lastYearStart->copy()->endOfMonth();

        return Cache::tags(["dashboard-shop-$shopId"])->remember(
            "shop-category-daily-sales:$shopId:{$monthStart->toDateString()}:{$until->toDateString()}",
            now()->addSeconds(300),
            function () use ($shopId, $monthStart, $until, $lastYearStart, $lastYearEnd) {
                $sales = [];
                $rows  = DB::table('invoices')
                    ->where('shop_id', $shopId)
                    ->where('in_process', false)
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->whereBetween('date', [$monthStart->toDateString(), $until->copy()->endOfDay()->toDateTimeString()])
                        ->orWhereBetween('date', [$lastYearStart->toDateString(), $lastYearEnd->toDateTimeString()]))
                    ->groupBy('invoice_category_id', DB::raw('cast(date as date)'))
                    ->selectRaw('coalesce(invoice_category_id, 0) as category_key, cast(date as date) as day, sum(org_net_amount) as sales')
                    ->get();

                foreach ($rows as $row) {
                    $series = $row->day >= $monthStart->toDateString() ? 'daily' : 'last_year_daily';
                    $sales[(int) $row->category_key] ??= ['daily' => [], 'last_year_daily' => []];
                    $sales[(int) $row->category_key][$series][(int) substr($row->day, 8, 2)] = (float) $row->sales;
                }

                return $sales;
            }
        );
    }

    /**
     * The shop's orders in the pipeline per the invoice category they will be invoiced under.
     *
     * @return array<int, array{amount: float, orders: int, submitted_amount: float, in_warehouse_amount: float}>
     */
    private function pipelineByCategory(Shop $shop): array
    {
        $categoriser = CategoriseInvoice::make();
        $categories  = $categoriser->getActiveInvoiceCategories($shop->organisation);
        $pipelines   = [];

        $orders = Order::where('shop_id', $shop->id)
            ->whereIn('state', self::PIPELINE_STATES)
            ->get(['id', 'shop_id', 'state', 'org_net_amount', 'billing_country_id', 'is_vip', 'as_organisation_id', 'sales_channel_id']);

        foreach ($orders as $order) {
            $order->setRelation('shop', $shop);
            $categoryKey = $categoriser->getInvoiceCategory($order, $categories)?->id ?? 0;
            $amountKey   = $order->state === OrderStateEnum::SUBMITTED ? 'submitted_amount' : 'in_warehouse_amount';

            $pipelines[$categoryKey] ??= ['amount' => 0.0, 'orders' => 0, 'submitted_amount' => 0.0, 'in_warehouse_amount' => 0.0];
            $pipelines[$categoryKey]['amount']     += (float) $order->org_net_amount;
            $pipelines[$categoryKey][$amountKey]   += (float) $order->org_net_amount;
            $pipelines[$categoryKey]['orders']++;
        }

        return array_map(fn (array $pipeline) => [...$pipeline, 'amount' => round($pipeline['amount'], 2), 'submitted_amount' => round($pipeline['submitted_amount'], 2), 'in_warehouse_amount' => round($pipeline['in_warehouse_amount'], 2)], $pipelines);
    }
}
