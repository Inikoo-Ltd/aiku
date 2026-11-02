<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock\UI;

use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Log of the SKO links removed by DisassociateEmptyLocationOrgStocks (HELP-4450).
 */
class IndexAutoDisassociatedLocationOrgStocks extends OrgAction
{
    public function handle(Warehouse $warehouse, string $prefix): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value)
                    ->orWhereStartWith('locations.code', $value);
            });
        });

        InertiaTable::updateQueryBuilderParameters($prefix);

        return QueryBuilder::for(OrgStockMovement::class)
            ->where('org_stock_movements.warehouse_id', $warehouse->id)
            ->where('org_stock_movements.type', OrgStockMovementTypeEnum::DISASSOCIATE)
            ->where('org_stock_movements.reason', OrgStockMovementReasonEnum::EMPTY_LOCATION)
            ->join('org_stocks', 'org_stocks.id', 'org_stock_movements.org_stock_id')
            ->join('locations', 'locations.id', 'org_stock_movements.location_id')
            ->defaultSort('-date')
            ->select([
                'org_stock_movements.id',
                'org_stock_movements.date',
                'org_stocks.slug',
                'org_stocks.code',
                'org_stocks.name',
                'locations.slug as location_slug',
                'locations.code as location_code',
            ])
            ->allowedSorts(['date', 'code', 'name', 'location_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table
                ->name($prefix)
                ->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('removal'), __('removals')])
                ->withEmptyState(['title' => __('Nothing removed automatically yet')])
                ->defaultSort('-date')
                ->column(key: 'date', label: __('Date'), sortable: true)
                ->column(key: 'location_code', label: __('Location'), sortable: true, searchable: true)
                ->column(key: 'code', label: __('SKO'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true);
        };
    }
}
