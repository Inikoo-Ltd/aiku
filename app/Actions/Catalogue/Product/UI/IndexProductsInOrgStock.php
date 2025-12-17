<?php

/*
 * author Arya Permana - Kirin
 * created on 27-05-2025-14h-42m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Inventory\OrgStock;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsInOrgStock extends OrgAction
{
    public function handle(OrgStock $orgStock, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->leftjoin('product_has_org_stocks', 'products.id', 'product_has_org_stocks.product_id');
        $queryBuilder->leftjoin('shops', 'shops.id', 'products.shop_id');
        $queryBuilder->leftjoin('organisations', 'organisations.id', 'products.organisation_id');

        $queryBuilder->leftjoin('currencies', 'currencies.id', 'shops.currency_id');


        $queryBuilder->where('product_has_org_stocks.org_stock_id', $orgStock->id);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');

        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.state',
                'products.price',
                'products.created_at',
                'products.updated_at',
                'products.asset_id',
                'products.slug',
                'products.master_product_id',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.code as organisation_code',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',

                'product_has_org_stocks.quantity',
                'trade_units_per_org_stock',
                'currencies.code as currency_code',
            ]);

        return $queryBuilder->allowedSorts(['code', 'name', 'price'])
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
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                    ]
                );


            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        };
    }

}
