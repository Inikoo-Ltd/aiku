<?php

/*
 * author Arya Permana - Kirin
 * created on 27-05-2025-13h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Inventory\OrgStock;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStocksInProduct extends OrgAction
{
    public function handle(Product $product, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->leftjoin('product_has_org_stocks', 'org_stocks.id', '=', 'product_has_org_stocks.org_stock_id')
            ->where('product_has_org_stocks.product_id', $product->id);

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.slug',
                'org_stocks.state',
                'org_stocks.unit_value',
                'product_has_org_stocks.quantity as quantity',
                'org_stocks.discontinued_in_organisation_at',
                'org_stock_families.slug as family_slug',
                'org_stock_families.code as family_code',
            ])
            ->leftJoin('org_stock_families', 'org_stocks.org_stock_family_id', 'org_stock_families.id')
            ->allowedSorts(['code', 'name', 'quantity'])
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
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations);

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'code', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true);
        };
    }
}
