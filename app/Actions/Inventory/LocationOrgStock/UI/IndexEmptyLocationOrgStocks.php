<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock\UI;

use App\Actions\Inventory\LocationOrgStock\DisassociateEmptyLocationOrgStocks;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The SKO links the next automatic run would remove (HELP-4450), shown before the switch is turned on.
 */
class IndexEmptyLocationOrgStocks extends OrgAction
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

        return QueryBuilder::for(DisassociateEmptyLocationOrgStocks::candidatesQuery($warehouse))
            ->defaultSort('location_code')
            ->select([
                'location_org_stocks.id',
                'org_stocks.slug',
                'org_stocks.code',
                'org_stocks.name',
                'locations.slug as location_slug',
                'locations.code as location_code',
            ])
            ->allowedSorts(['code', 'name', 'location_code'])
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
                ->withLabelRecord([__('empty location'), __('empty locations')])
                ->withEmptyState(['title' => __('No empty locations to remove')])
                ->defaultSort('location_code')
                ->column(key: 'location_code', label: __('Location'), sortable: true, searchable: true)
                ->column(key: 'code', label: __('SKO'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true);
        };
    }
}
