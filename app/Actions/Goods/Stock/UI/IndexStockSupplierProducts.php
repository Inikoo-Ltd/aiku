<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\Stock;
use App\Models\Goods\StockHasSupplierProduct;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexStockSupplierProducts extends OrgAction
{
    public function handle(Stock $stock, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('supplier_products.code', $value)
                    ->orWhereAnyWordStartWith('supplier_products.name', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value)
                    ->orWhereAnyWordStartWith('agents.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(StockHasSupplierProduct::class);
        $query->where('stock_has_supplier_products.stock_id', $stock->id);
        $query->leftJoin('supplier_products', 'supplier_products.id', 'stock_has_supplier_products.supplier_product_id');
        $query->leftJoin('suppliers', 'suppliers.id', 'supplier_products.supplier_id');
        $query->leftJoin('agents', 'agents.id', 'suppliers.agent_id');
        $query->leftJoin('currencies', 'currencies.id', 'supplier_products.currency_id');

        return $query
            ->defaultSort('-priority')
            ->select([
                'stock_has_supplier_products.stock_id',
                'stock_has_supplier_products.supplier_product_id',
                'stock_has_supplier_products.priority',
                'supplier_products.code',
                'supplier_products.name',
                'supplier_products.description',
                'supplier_products.cost as unit_cost',
                'supplier_products.units_per_carton',
                'suppliers.name as supplier_name',
                'suppliers.slug as supplier_slug',
                'agents.name as agent_name',
                'agents.slug as agent_slug',
                'currencies.code as currency_code',
            ])
            ->selectRaw('(stock_has_supplier_products.priority > 0 and stock_has_supplier_products.priority = max(stock_has_supplier_products.priority) over ()) as is_preferred')
            ->selectRaw("(
                select string_agg(organisations.code, ', ' order by organisations.code)
                from org_stock_has_org_supplier_products link
                join org_stocks on org_stocks.id = link.org_stock_id
                join organisations on organisations.id = org_stocks.organisation_id
                where link.stock_has_supplier_product_id = stock_has_supplier_products.id and link.status
                and link.local_priority = (select max(rival.local_priority) from org_stock_has_org_supplier_products rival where rival.org_stock_id = link.org_stock_id and rival.status)
            ) as preferred_in")
            ->allowedSorts([
                'code',
                'priority',
                AllowedSort::field('supplier_name', 'suppliers.name'),
                AllowedSort::field('agent_name', 'agents.name'),
                AllowedSort::field('unit_cost', 'supplier_products.cost'),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__("Supplier's product"), __("Supplier's products")])
                ->column(key: 'preferred', label: '', type: 'icon')
                ->column(key: 'supplier_name', label: __('Supplier'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'agent_name', label: __('Agent'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'code', label: __("Supplier's code"), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'description', label: __("Supplier's unit description"), canBeHidden: false, searchable: true)
                ->column(key: 'unit_cost', label: __('Unit cost'), canBeHidden: false, sortable: true, type: 'currency')
                ->column(key: 'preferred_in', label: __('Preferred in'), canBeHidden: false)
                ->column(key: 'set_preferred', label: '', align: 'right')
                ->defaultSort('-priority');
        };
    }
}
