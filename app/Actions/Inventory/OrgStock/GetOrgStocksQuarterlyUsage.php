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
     * SKOs dispatched per quarter for the last four quarters, keyed by org stock id.
     *
     * @param  Collection<int, int>  $orgStockIds
     * @return Collection<int, Collection<int, array{period: string, sales: float}>>
     */
    public function handle(Collection $orgStockIds): Collection
    {
        if ($orgStockIds->isEmpty()) {
            return collect();
        }

        return DB::table('delivery_note_items')
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('quantity_dispatched', '>', 0)
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw("org_stock_id, to_char(date_trunc('quarter', created_at), 'YYYY\"Q\"Q') as period, sum(quantity_dispatched) as sales")
            ->groupByRaw("org_stock_id, date_trunc('quarter', created_at)")
            ->orderByRaw("date_trunc('quarter', created_at)")
            ->get()
            ->groupBy('org_stock_id')
            ->map(fn ($records) => $records->take(-4)->values()->map(fn ($record) => [
                'period' => $record->period,
                'sales'  => round((float) $record->sales, 1),
            ]));
    }
}
