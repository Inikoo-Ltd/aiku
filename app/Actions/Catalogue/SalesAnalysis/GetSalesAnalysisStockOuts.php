<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\SalesAnalysis;

use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSalesAnalysisStockOuts
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
     * @param array<int, int> $productIds
     * @return array<int, array{org_stock_id: int, code: string, organisation: string, organisation_id: int, started_on: string, back_in_on: string|null, days: int, approximate: bool, cause: string, order: array|null, days_to_order: int|null, lost_sales: float, websites: int}>
     */
    public function handle(array $productIds, Carbon $from, Carbon $to, string $amountColumn = 'grp_net_amount'): array
    {
        return $this->handlePeriods($productIds, [[$from, $to]], $amountColumn)[0];
    }

    /**
     * The stock outs of several periods read in one pass over the stock history.
     *
     * @param array<int, int> $productIds
     * @param array<int, array{0: Carbon, 1: Carbon}> $periods
     * @return array<int, array> stock outs of each period, in the order of $periods
     */
    public function handlePeriods(array $productIds, array $periods, string $amountColumn = 'grp_net_amount'): array
    {
        $results = array_fill(0, count($periods), []);
        if (!$productIds) {
            return $results;
        }

        $start = collect($periods)->map(fn ($period) => $period[0])->min();
        $end   = collect($periods)->map(fn ($period) => $period[1])->max();

        $runs = $this->runs($this->productOrgStocks($productIds)->distinct()->pluck('product_has_org_stocks.org_stock_id')->all(), $start, $end);
        if (!$runs) {
            return $results;
        }

        $orgStockIds   = array_values(array_unique(array_column($runs, 'org_stock_id')));
        $orgStocks     = $this->orgStocks($productIds, $orgStockIds);
        $orders        = $this->orders($orgStockIds);
        $deliveries    = $this->deliveries($orgStockIds);
        $dailySales    = $this->dailySales($productIds, $orgStockIds, $start->copy()->subDays(self::SALES_RATE_DAYS), $end, match ($amountColumn) {
            'net_amount' => 'sales_external',
            'org_net_amount' => 'sales_org_currency_external',
            default => 'sales_grp_currency_external',
        });
        $stockedBefore = $this->stockedBefore(collect($runs)->where('starts_at_period_start', true)->pluck('org_stock_id')->unique()->all(), $start);

        foreach ($runs as $run) {
            if ($run->starts_at_period_start && !isset($stockedBefore[$run->org_stock_id])) {
                continue;
            }

            $orgStock       = $orgStocks[$run->org_stock_id];
            $started        = Carbon::parse($run->started_on);
            $backIn         = $run->back_in_on ? Carbon::parse($run->back_in_on) : null;
            $isDiscontinued = !$backIn && in_array($orgStock->state, [OrgStockStateEnum::DISCONTINUED->value, OrgStockStateEnum::DISCONTINUING->value]);
            $dailyRate      = $isDiscontinued ? 0.0 : $this->averageDailySales($dailySales->get($run->org_stock_id, []), $started);
            $cause          = null;

            foreach ($periods as $index => [$from, $to]) {
                $periodEnd = $to->copy()->addDay();
                if ($started->gte($periodEnd) || ($backIn && $backIn->lte($from))) {
                    continue;
                }

                $days = (int)$started->copy()->max($from)->diffInDays($backIn && $backIn->lt($periodEnd) ? $backIn : $periodEnd);
                if ($days < self::MINIMUM_DAYS) {
                    continue;
                }

                $cause ??= $isDiscontinued
                    ? ['discontinued', null, null]
                    : $this->cause(
                        $orders->get($run->org_stock_id.'-'.$run->organisation_id, collect()),
                        $deliveries->get($run->org_stock_id.'-'.$run->organisation_id, []),
                        $started,
                        $backIn ?? $end
                    );

                $results[$index][] = [
                    'org_stock_id'      => $run->org_stock_id,
                    'code'              => $orgStock->code,
                    'organisation'      => $orgStock->organisation_name,
                    'organisation_id'   => $run->organisation_id,
                    'organisation_slug' => $orgStock->organisation_slug,
                    'started_on'        => $started->toDateString(),
                    'back_in_on'        => $backIn && $backIn->lt($periodEnd) ? $backIn->toDateString() : null,
                    'days'              => $days,
                    'approximate'       => (bool)$run->approximate,
                    'cause'             => $cause[0],
                    'order'             => $cause[1],
                    'days_to_order'     => $cause[2],
                    'lost_sales'        => round($dailyRate * min($days, self::MAX_LOST_SALES_DAYS), 2),
                    'websites'          => $orgStock->websites,
                ];
            }
        }

        return array_map(function (array $stockOuts) {
            usort($stockOuts, fn ($a, $b) => [$b['started_on'], $a['org_stock_id']] <=> [$a['started_on'], $b['org_stock_id']]);

            return $stockOuts;
        }, $results);
    }

    private function orgStocks(array $productIds, array $orgStockIds): Collection
    {
        $websites = $this->productOrgStocks($productIds)
            ->whereIn('product_has_org_stocks.org_stock_id', $orgStockIds)
            ->where('products.state', 'active')
            ->groupBy('product_has_org_stocks.org_stock_id')
            ->selectRaw('product_has_org_stocks.org_stock_id, count(distinct products.shop_id) as websites')
            ->pluck('websites', 'org_stock_id');

        return DB::table('org_stocks')
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->whereIn('org_stocks.id', $orgStockIds)
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

    private function productOrgStocks(array $productIds): Builder
    {
        return DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->whereRaw('products.id = any(?::int[])', ['{'.implode(',', $productIds).'}']);
    }

    /**
     * Runs of days out of stock (gaps and islands). Only the SKOs out of stock at some point are
     * read, found through the partial index on out of stock days.
     */
    private function runs(array $orgStockIds, Carbon $from, Carbon $to): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $outOrgStockIds = DB::table('org_stock_histories')
            ->whereRaw('org_stock_id = any(?::int[])', ['{'.implode(',', $orgStockIds).'}'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereRaw('quantity_in_locations <= 0')
            ->distinct()
            ->pluck('org_stock_id')
            ->all();
        if (!$outOrgStockIds) {
            return [];
        }

        return DB::select(
            <<<'SQL'
            select org_stock_id, min(organisation_id) as organisation_id, min(date) as started_on,
                   max(next_date) filter (where next_is_out is distinct from true) as back_in_on,
                   bool_or(next_date - date > 1 or date - previous_date > 1) as approximate,
                   bool_or(previous_date is null) as starts_at_period_start
            from (
                select *, count(*) filter (where is_out is distinct from previous_is_out) over w as run
                from (
                    select org_stock_id, organisation_id, date, quantity_in_locations <= 0 as is_out,
                           lag(quantity_in_locations <= 0) over w as previous_is_out, lead(quantity_in_locations <= 0) over w as next_is_out,
                           lead(date) over w as next_date, lag(date) over w as previous_date
                    from org_stock_histories
                    where org_stock_id = any(?) and date between ? and ?
                    window w as (partition by org_stock_id order by date)
                ) histories
                window w as (partition by org_stock_id order by date)
            ) runs
            where is_out
            group by org_stock_id, run
            SQL,
            ['{'.implode(',', $outOrgStockIds).'}', $from->toDateString(), $to->toDateString()]
        );
    }

    private function stockedBefore(array $orgStockIds, Carbon $from): array
    {
        if (!$orgStockIds) {
            return [];
        }

        return DB::table('org_stock_histories')
            ->whereRaw('org_stock_id = any(?::int[])', ['{'.implode(',', $orgStockIds).'}'])
            ->where('date', '<', $from->toDateString())
            ->where('quantity_in_locations', '>', 0)
            ->distinct()
            ->pluck('org_stock_id')
            ->flip()
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
            ->each(fn ($order) => $order->ordered_timestamp = Carbon::parse($order->ordered_at)->getTimestamp())
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
            ->groupBy(fn ($delivery) => $delivery->org_stock_id.'-'.$delivery->organisation_id)
            ->map(fn (Collection $deliveries) => $deliveries->map(fn ($delivery) => Carbon::parse($delivery->received_at)->getTimestamp())->all());
    }

    /**
     * @param array<int, int> $deliveries timestamps the SKO was received
     * @return array{0: string, 1: array|null, 2: int|null}
     */
    private function cause(Collection $orders, array $deliveries, Carbon $started, Carbon $until): array
    {
        if ($started->lt(Carbon::parse(self::PURCHASE_HISTORY_FROM))) {
            return ['unknown', null, null];
        }

        if ($orders->isEmpty()) {
            return ['restocked_directly', null, null];
        }

        $startedAt       = $started->getTimestamp();
        $openOrdersSince = $started->copy()->subDays(self::OPEN_ORDER_DAYS)->getTimestamp();

        $openOrder = $orders
            ->filter(function ($order) use ($startedAt, $openOrdersSince, $deliveries) {
                if ($order->ordered_timestamp > $startedAt || $order->ordered_timestamp < $openOrdersSince) {
                    return false;
                }
                foreach ($deliveries as $receivedAt) {
                    if ($receivedAt >= $order->ordered_timestamp && $receivedAt <= $startedAt) {
                        return false;
                    }
                }

                return true;
            })
            ->sortByDesc('ordered_at')
            ->first();

        if ($openOrder) {
            return [$this->expectedArrival($openOrder)->lt($started) ? 'supplier_late' : 'ordered_too_late', $this->orderData($openOrder), null];
        }

        $untilAt    = $until->getTimestamp();
        $laterOrder = $orders
            ->filter(fn ($order) => $order->ordered_timestamp >= $startedAt && $order->ordered_timestamp <= $untilAt)
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

    private function dailySales(array $productIds, array $orgStockIds, Carbon $from, Carbon $to, string $salesColumn): Collection
    {
        return $this->productOrgStocks($productIds)
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
            ->selectRaw("product_has_org_stocks.org_stock_id, asset_time_series_records.from::text as date, sum(asset_time_series_records.$salesColumn) as sales")
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
