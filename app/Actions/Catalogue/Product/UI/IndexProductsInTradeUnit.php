<?php
/*
 * author Arya Permana - Kirin
 * created on 27-05-2025-13h-55m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
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
        $queryBuilder->where('model_has_trade_units.trade_unit_id', $tradeUnit->id);

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
                'products.slug',
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
            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('price'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

}
