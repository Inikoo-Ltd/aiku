<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Dec 2025 14:30:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStocksInStock extends OrgAction
{
    public function handle(Stock $stock, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(OrgStock::class);
        $queryBuilder->where('stock_id', $stock->id);

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.slug',
                'org_stocks.state',
                'org_stocks.packed_in',
                'org_stocks.discontinued_in_organisation_at',
                'org_stock_families.slug as family_slug',
                'org_stock_families.code as family_code',
                'organisations.code as organisation_code',
                'organisations.name as organisation_name',
                'org_stocks.organisation_id as organisation_id',
                'org_stocks.quantity_available as quantity_available',
                'org_stocks.quantity_in_locations as quantity_in_locations',
            ])
            ->leftJoin('org_stock_families', 'org_stocks.org_stock_family_id', 'org_stock_families.id')
            ->leftJoin('organisations', 'org_stocks.organisation_id', 'organisations.id')
            ->allowedSorts(['code', 'name','quantity_available','quantity_in_locations','packed_in'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withLabelRecord([__('SKU'), __('SKUs')])
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations);

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'org_sku', label: __('Organisation'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'packed_in', label: __('Units per SKU'), canBeHidden: false, sortable: true, searchable: true, align: 'right');

            $table->column(key: 'quantity_in_locations', label: __('Stock in Locations'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'quantity_available', label: __('Stock Available'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        };
    }
}
