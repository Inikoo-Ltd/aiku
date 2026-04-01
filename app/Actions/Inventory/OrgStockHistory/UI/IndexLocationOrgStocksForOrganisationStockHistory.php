<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Inventory\OrgStockHistory\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Inventory\LocationOrgStockHistoriesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\LocationOrgStockHistory;
use App\Models\Inventory\OrganisationStockHistory;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexLocationOrgStocksForOrganisationStockHistory extends OrgAction
{
    public function handle(OrganisationStockHistory $organisationStockHistory, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value)
                    ->orWhereStartWith('locations.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(LocationOrgStockHistory::class)
            ->leftJoin('org_stock_histories', 'location_org_stock_histories.org_stock_history_id', '=', 'org_stock_histories.id')
            ->leftJoin('org_stocks', 'location_org_stock_histories.org_stock_id', '=', 'org_stocks.id')
            ->leftJoin('locations', 'location_org_stock_histories.location_id', '=', 'locations.id')
            ->where('org_stock_histories.organisation_stock_history_id', $organisationStockHistory->id)
            ->select([
                'location_org_stock_histories.id',
                'org_stocks.id as stock_id',
                'org_stocks.code as stock_code',
                'org_stocks.name as stock_name',
                'org_stocks.slug as stock_slug',
                'locations.code as location_code',
                'location_org_stock_histories.quantity_in_locations',
                'location_org_stock_histories.org_stock_value',
                DB::raw("'" . $organisationStockHistory->organisation->currency->code . "' as currency_code"),
            ])
            ->defaultSort('org_stocks.code')
            ->allowedSorts(['stock_code', 'location_code', 'quantity_in_locations', 'org_stock_value'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withTitle(title: __('Locations'))
                ->withLabelRecord([__('record'), __('records')])
                ->withGlobalSearch()
                ->column(key: 'stock_code', label: __('SKU'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location_code', label: __('Location'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity_in_locations', label: __('Quantity'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'org_stock_value', label: __('Stock Value'), canBeHidden: false, sortable: true, type: 'currency', align: 'right')
                ->defaultSort('stock_code');
        };
    }
}
