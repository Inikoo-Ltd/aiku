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
     * SKOs dispatched and days out of stock in each of the last four quarters (the current one included), keyed by org stock id.
     *
     * @param  Collection<int, int>  $orgStockIds
     * @return Collection<int, Collection<int, array{period: string, sales: float, days_out_of_stock: int}>>
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
            ]),
        ]);
    }
}
