<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Masters\MasterProductCategory;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMasterFamilyStockOuts
{
    use AsAction;

    private const int MINIMUM_DAYS = 2;
    private const int SALES_RATE_DAYS = 180;
    private const int MAX_LOST_SALES_DAYS = 90;
    private const int OPEN_ORDER_DAYS = 180;
    private const int DEFAULT_PURCHASE_ORDER_LEAD_DAYS = 90;
    private const int DEFAULT_STOCK_DELIVERY_LEAD_DAYS = 14;
    private const string PURCHASE_HISTORY_FROM = '2016-10-01';

    /**
     * @return array<int, array{org_stock_id: int, code: string, organisation: string, organisation_id: int, started_on: string, back_in_on: string|null, days: int, approximate: bool, cause: string, order: array|null, days_to_order: int|null, lost_sales: float, websites: int}>
     */
    public function handle(MasterProductCategory $masterFamily, Carbon $from, Carbon $to): array
    {
        $orgStocks = $this->orgStocks($masterFamily);
        if ($orgStocks->isEmpty()) {
            return [];
        }

        $orgStockIds  = $orgStocks->keys()->all();
        $firstStocked = $this->firstStocked($orgStockIds);
        $orders       = $this->orders($orgStockIds);
        $deliveries   = $this->deliveries($orgStockIds);
        $dailySales   = $this->dailySales($masterFamily, $orgStockIds, $from->copy()->subDays(self::SALES_RATE_DAYS), $to);

        $stockOuts = [];
        foreach ($this->runs($orgStockIds, $from, $to) as $run) {
            $orgStock = $orgStocks[$run->org_stock_id];
            $started  = Carbon::parse($run->started_on);
            $backIn   = $run->back_in_on ? Carbon::parse($run->back_in_on) : null;
            $days     = (int)$started->diffInDays($backIn ?? $to->copy()->addDay());

            $neverStocked = !isset($firstStocked[$run->org_stock_id]) || $firstStocked[$run->org_stock_id] > $run->started_on;
            if ($neverStocked || $days < self::MINIMUM_DAYS) {
                continue;
            }

            $isDiscontinued = !$backIn && in_array($orgStock->state, [OrgStockStateEnum::DISCONTINUED->value, OrgStockStateEnum::DISCONTINUING->value]);

            [$cause, $order, $daysToOrder] = $isDiscontinued
                ? ['discontinued', null, null]
                : $this->cause(
                    $orders->get($run->org_stock_id.'-'.$run->organisation_id, collect()),
                    $deliveries->get($run->org_stock_id.'-'.$run->organisation_id, collect()),
                    $started,
                    $backIn ?? $to
                );

            $stockOuts[] = [
                'org_stock_id'    => $run->org_stock_id,
                'code'            => $orgStock->code,
                'organisation'    => $orgStock->organisation_name,
                'organisation_id' => $run->organisation_id,
                'organisation_slug' => $orgStock->organisation_slug,
                'started_on'      => $started->toDateString(),
                'back_in_on'      => $backIn?->toDateString(),
                'days'            => $days,
                'approximate'     => (bool)$run->approximate,
                'cause'           => $cause,
                'order'           => $order,
                'days_to_order'   => $daysToOrder,
                'lost_sales'      => $isDiscontinued ? 0.0 : round($this->averageDailySales($dailySales->get($run->org_stock_id, []), $started) * min($days, self::MAX_LOST_SALES_DAYS), 2),
                'websites'        => $orgStock->websites,
            ];
        }

        usort($stockOuts, fn ($a, $b) => $b['started_on'] <=> $a['started_on']);

        return $stockOuts;
    }

    private function orgStocks(MasterProductCategory $masterFamily): Collection
    {
        $websites = $this->familyProductOrgStocks($masterFamily)
            ->where('products.state', 'active')
            ->groupBy('product_has_org_stocks.org_stock_id')
            ->selectRaw('product_has_org_stocks.org_stock_id, count(distinct products.shop_id) as websites')
            ->pluck('websites', 'org_stock_id');

        return DB::table('org_stocks')
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->whereIn('org_stocks.id', $this->familyProductOrgStocks($masterFamily)->select('product_has_org_stocks.org_stock_id'))
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.state',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->get()
            ->each(fn ($orgStock) => $orgStock->websites = (int)($websites[$orgStock->id] ?? 0))
            ->keyBy('id');
    }

    private function familyProductOrgStocks(MasterProductCategory $masterFamily): Builder
    {
        return DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->where(function ($query) use ($masterFamily) {
                $query->whereIn('products.master_product_id', DB::table('master_assets')->where('master_family_id', $masterFamily->id)->select('id'))
                    ->orWhereIn('products.family_id', DB::table('product_categories')->where('master_product_category_id', $masterFamily->id)->select('id'));
            });
    }

    private function runs(array $orgStockIds, Carbon $from, Carbon $to): array
    {
        return DB::select(
            <<<'SQL'
            select org_stock_id, organisation_id, min(date) as started_on, max(next_date) filter (where is_last) as back_in_on,
                   bool_or(next_date - date > 1 or date - previous_date > 1) as approximate
            from (
                select *, lead(is_out) over w is distinct from true as is_last
                from (
                    select org_stock_id, organisation_id, date, quantity_in_locations <= 0 as is_out,
                           lead(date) over w as next_date, lag(date) over w as previous_date,
                           row_number() over w - row_number() over (partition by org_stock_id, organisation_id, quantity_in_locations <= 0 order by date) as run
                    from org_stock_histories
                    where org_stock_id = any(?) and date between ? and ?
                    window w as (partition by org_stock_id, organisation_id order by date)
                ) histories
                window w as (partition by org_stock_id, organisation_id order by date)
            ) runs
            where is_out
            group by org_stock_id, organisation_id, run
            SQL,
            ['{'.implode(',', $orgStockIds).'}', $from->toDateString(), $to->toDateString()]
        );
    }

    private function firstStocked(array $orgStockIds): array
    {
        return DB::table('org_stock_histories')
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('quantity_in_locations', '>', 0)
            ->groupBy('org_stock_id')
            ->selectRaw('org_stock_id, min(date)::text as first_stocked')
            ->pluck('first_stocked', 'org_stock_id')
            ->all();
    }

    private function orders(array $orgStockIds): Collection
    {
        $purchaseOrders = DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->whereIn('purchase_order_transactions.org_stock_id', $orgStockIds)
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereNull('purchase_orders.deleted_at')
            ->whereNotIn('purchase_orders.state', [PurchaseOrderStateEnum::IN_PROCESS->value, PurchaseOrderStateEnum::CANCELLED->value])
            ->select([
                'purchase_order_transactions.org_stock_id',
                'purchase_orders.organisation_id',
                'purchase_orders.reference',
                'purchase_orders.slug',
                'purchase_orders.parent_name as supplier',
                'purchase_orders.estimated_received_at',
            ])
            ->selectRaw("'purchase_order' as kind, coalesce(purchase_orders.submitted_at, purchase_orders.date) as ordered_at")
            ->get();

        $stockDeliveries = DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->whereIn('stock_delivery_items.org_stock_id', $orgStockIds)
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('stock_deliveries.deleted_at')
            ->whereNull('stock_deliveries.cancelled_at')
            ->select([
                'stock_delivery_items.org_stock_id',
                'stock_deliveries.organisation_id',
                'stock_deliveries.reference',
                'stock_deliveries.slug',
                'stock_deliveries.parent_name as supplier',
            ])
            ->selectRaw("null as estimated_received_at, 'stock_delivery' as kind, coalesce(stock_deliveries.dispatched_at, stock_deliveries.date, stock_deliveries.created_at) as ordered_at")
            ->get();

        return $purchaseOrders->concat($stockDeliveries)
            ->groupBy(fn ($order) => $order->org_stock_id.'-'.$order->organisation_id)
            ->map(fn (Collection $orders) => $orders->contains('kind', 'purchase_order') ? $orders->where('kind', 'purchase_order')->values() : $orders);
    }

    private function deliveries(array $orgStockIds): Collection
    {
        return DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->whereIn('stock_delivery_items.org_stock_id', $orgStockIds)
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('stock_deliveries.deleted_at')
            ->whereRaw('coalesce(stock_delivery_items.received_at, stock_deliveries.received_at, stock_deliveries.placed_at) is not null')
            ->select(['stock_delivery_items.org_stock_id', 'stock_delivery_items.organisation_id'])
            ->selectRaw('coalesce(stock_delivery_items.received_at, stock_deliveries.received_at, stock_deliveries.placed_at) as received_at')
            ->get()
            ->groupBy(fn ($delivery) => $delivery->org_stock_id.'-'.$delivery->organisation_id);
    }

    /**
     * @return array{0: string, 1: array|null, 2: int|null}
     */
    private function cause(Collection $orders, Collection $deliveries, Carbon $started, Carbon $until): array
    {
        if ($started->lt(Carbon::parse(self::PURCHASE_HISTORY_FROM))) {
            return ['unknown', null, null];
        }

        if ($orders->isEmpty()) {
            return ['restocked_directly', null, null];
        }

        $openOrder = $orders
            ->filter(function ($order) use ($started, $deliveries) {
                $orderedAt = Carbon::parse($order->ordered_at);

                return $orderedAt->lte($started)
                    && $orderedAt->gte($started->copy()->subDays(self::OPEN_ORDER_DAYS))
                    && !$deliveries->contains(fn ($delivery) => Carbon::parse($delivery->received_at)->between($orderedAt, $started));
            })
            ->sortByDesc('ordered_at')
            ->first();

        if ($openOrder) {
            return [$this->expectedArrival($openOrder)->lt($started) ? 'supplier_late' : 'ordered_too_late', $this->orderData($openOrder), null];
        }

        $laterOrder = $orders
            ->filter(fn ($order) => Carbon::parse($order->ordered_at)->between($started, $until))
            ->sortBy('ordered_at')
            ->first();

        if ($laterOrder) {
            return ['ordered_after', $this->orderData($laterOrder), (int)$started->diffInDays(Carbon::parse($laterOrder->ordered_at))];
        }

        return ['no_order', null, null];
    }

    private function expectedArrival(object $order): Carbon
    {
        if ($order->estimated_received_at) {
            return Carbon::parse($order->estimated_received_at);
        }

        return Carbon::parse($order->ordered_at)->addDays(
            $order->kind === 'stock_delivery' ? self::DEFAULT_STOCK_DELIVERY_LEAD_DAYS : self::DEFAULT_PURCHASE_ORDER_LEAD_DAYS
        );
    }

    private function orderData(object $order): array
    {
        return [
            'kind'        => $order->kind,
            'reference'   => $order->reference,
            'slug'        => $order->slug,
            'supplier'    => $order->supplier,
            'ordered_on'  => Carbon::parse($order->ordered_at)->toDateString(),
            'expected_on' => $this->expectedArrival($order)->toDateString(),
            'expected_is_estimate' => !$order->estimated_received_at,
        ];
    }

    private function dailySales(MasterProductCategory $masterFamily, array $orgStockIds, Carbon $from, Carbon $to): Collection
    {
        return $this->familyProductOrgStocks($masterFamily)
            ->join('asset_time_series', function ($join) {
                $join->on('asset_time_series.asset_id', 'products.asset_id')->where('asset_time_series.frequency', 'daily');
            })
            ->join('asset_time_series_records', function ($join) use ($from, $to) {
                $join->on('asset_time_series_records.asset_time_series_id', 'asset_time_series.id')
                    ->where('asset_time_series_records.frequency', 'D')
                    ->whereBetween('asset_time_series_records.from', [$from->toDateString(), $to->toDateString()]);
            })
            ->whereIn('product_has_org_stocks.org_stock_id', $orgStockIds)
            ->groupBy('product_has_org_stocks.org_stock_id', 'asset_time_series_records.from')
            ->selectRaw('product_has_org_stocks.org_stock_id, asset_time_series_records.from::text as date, sum(asset_time_series_records.sales_grp_currency_external) as sales')
            ->get()
            ->groupBy('org_stock_id')
            ->map(fn (Collection $rows) => $rows->pluck('sales', 'date')->all());
    }

    private function averageDailySales(array $salesByDate, Carbon $started): float
    {
        $windowStart = $started->copy()->subDays(self::SALES_RATE_DAYS)->toDateString();
        $windowEnd   = $started->toDateString();

        $total = 0.0;
        foreach ($salesByDate as $date => $sales) {
            if ($date >= $windowStart && $date < $windowEnd) {
                $total += (float)$sales;
            }
        }

        return $total / self::SALES_RATE_DAYS;
    }
}
