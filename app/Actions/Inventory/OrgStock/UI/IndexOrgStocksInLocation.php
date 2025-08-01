<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 08:08:18 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStocksInLocation extends OrgAction
{
    use WithInventoryAuthorisation;


    public function handle(Location $location, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(LocationOrgStock::class);

        $queryBuilder->where('location_org_stocks.location_id', $location->id)
            ->leftJoin('org_stocks', 'location_org_stocks.org_stock_id', 'org_stocks.id');

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.slug',
                'org_stocks.unit_value',
                'number_locations',
                'quantity_in_locations',
                'org_stocks.discontinued_in_organisation_at'
            ])

            ->allowedSorts(['code', 'name', 'family_code', 'unit_value', 'discontinued_in_organisation_at'])
            ->allowedFilters([$globalSearch, AllowedFilter::exact('state')])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Location $location, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($location, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->column(key: 'code', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);


            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'unit_value', label: __('unit value'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'stock', label: __('stock'), canBeHidden: false, sortable: true, searchable: true);
        };
    }


}
