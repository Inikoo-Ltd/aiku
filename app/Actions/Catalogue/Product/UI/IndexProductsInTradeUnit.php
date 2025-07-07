<?php

/*
 * author Arya Permana - Kirin
 * created on 27-05-2025-13h-55m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsInTradeUnit extends OrgAction
{
    public function handle(TradeUnit $tradeUnit, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->leftjoin('model_has_trade_units', function ($join) use ($tradeUnit) {
            $join->on('products.id', '=', 'model_has_trade_units.model_id')
                ->where('model_has_trade_units.model_type', class_basename(Product::class));
        });
        $queryBuilder->leftJoin('asset_sales_intervals', 'products.asset_id', 'asset_sales_intervals.asset_id');
        $queryBuilder->leftJoin('shops', 'products.shop_id', '=', 'shops.id')
            ->leftJoin('organisations', 'products.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('currencies', 'products.currency_id', '=', 'currencies.id');
        $queryBuilder->where('model_has_trade_units.trade_unit_id', $tradeUnit->id);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');

        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'currencies.code as  currency_code',
                'sales_all',
                'products.id',
                'products.code',
                'products.name',
                'products.state',
                'products.price',
                'products.created_at',
                'products.updated_at',
                'products.slug',
                'shops.code as shop_code',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.code as organisation_code',
                'organisations.slug as organisation_slug',
            ]);

        return $queryBuilder->allowedSorts(['code', 'name', 'price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null, string $bucket = null): Closure
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


            $table->column(key: 'state', label: __('state'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'organisation_code', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'shop_code', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sales_all', label: __('price'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

}
