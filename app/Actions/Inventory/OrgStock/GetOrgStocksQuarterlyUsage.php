<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStocksQuarterlyUsage
{
    use AsObject;

    /**
     * SKOs dispatched, days out of stock and the customer who took the most in each of the last four quarters (the current one included), keyed by org stock id.
     *
     * @param  Collection<int, int>  $orgStockIds
     * @return Collection<int, Collection<int, array{period: string, sales: float, days_out_of_stock: int, top_customer: array{id: int, name: string, sales: float}|null}>>
     */
    public function handle(Collection $orgStockIds): Collection
    {
        if ($orgStockIds->isEmpty()) {
            return collect();
        }

        $from    = now()->firstOfQuarter()->subQuarters(3)->startOfDay();
        $periods = collect(range(0, 3))->map(fn (int $quarter) => $from->copy()->addQuarters($quarter))
            ->map(fn ($start) => $start->year.'Q'.$start->quarter);

        $sales = DB::table('delivery_note_items')
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('quantity_dispatched', '>', 0)
            ->where('created_at', '>=', $from)
            ->selectRaw("org_stock_id, to_char(date_trunc('quarter', created_at), 'YYYY\"Q\"Q') as period, sum(quantity_dispatched) as sales")
            ->groupByRaw("org_stock_id, date_trunc('quarter', created_at)")
            ->get()
            ->groupBy('org_stock_id')
            ->map(fn ($records) => $records->pluck('sales', 'period'));

        $topCustomers = DB::query()
            ->fromSub(
                DB::table('delivery_note_items')
                    ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->whereIn('delivery_note_items.org_stock_id', $orgStockIds)
                    ->where('delivery_note_items.quantity_dispatched', '>', 0)
                    ->where('delivery_note_items.created_at', '>=', $from)
                    ->whereNotNull('delivery_notes.customer_id')
                    ->selectRaw("delivery_note_items.org_stock_id, to_char(date_trunc('quarter', delivery_note_items.created_at), 'YYYY\"Q\"Q') as period, delivery_notes.customer_id, sum(delivery_note_items.quantity_dispatched) as sales")
                    ->groupByRaw("delivery_note_items.org_stock_id, date_trunc('quarter', delivery_note_items.created_at), delivery_notes.customer_id"),
                'customer_sales'
            )
            ->join('customers', 'customers.id', '=', 'customer_sales.customer_id')
            ->selectRaw('distinct on (customer_sales.org_stock_id, customer_sales.period) customer_sales.org_stock_id, customer_sales.period, customer_sales.customer_id, customer_sales.sales, customers.name')
            ->orderByRaw('customer_sales.org_stock_id, customer_sales.period, customer_sales.sales desc, customer_sales.customer_id')
            ->get()
            ->groupBy('org_stock_id')
            ->map(fn ($records) => $records->keyBy('period'));

        $daysOutOfStock = DB::table('org_stock_histories')
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('quantity_in_locations', '<=', 0)
            ->where('date', '>=', $from)
            ->selectRaw("org_stock_id, to_char(date_trunc('quarter', date), 'YYYY\"Q\"Q') as period, count(*) as days")
            ->groupByRaw("org_stock_id, date_trunc('quarter', date)")
            ->get()
            ->groupBy('org_stock_id')
            ->map(fn ($records) => $records->pluck('days', 'period'));

        return $orgStockIds->mapWithKeys(fn ($orgStockId) => [
            $orgStockId => $periods->map(fn (string $period) => [
                'period'            => $period,
                'sales'             => round((float) ($sales->get($orgStockId)?->get($period) ?? 0), 1),
                'days_out_of_stock' => (int) ($daysOutOfStock->get($orgStockId)?->get($period) ?? 0),
                'top_customer'      => $this->topCustomer($topCustomers->get($orgStockId)?->get($period)),
            ]),
        ]);
    }

    /**
     * @return array{id: int, name: string, sales: float}|null
     */
    private function topCustomer(?object $record): ?array
    {
        if (!$record) {
            return null;
        }

        return [
            'id'        => (int) $record->customer_id,
            'name'      => $record->name,
            'sales'     => round((float) $record->sales, 1),
        ];
    }
}
