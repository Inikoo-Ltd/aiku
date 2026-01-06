<?php

/*
 * author Louis Perez
 * created on 06-01-2026-11h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Variant;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsInVariant extends OrgAction
{

    public function handle(Variant $variant, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->orderBy('products.state');
        $queryBuilder->leftJoin('shops', 'products.shop_id', 'shops.id');
        $queryBuilder->leftJoin('currencies', 'shops.currency_id', 'currencies.id');
        $queryBuilder->leftJoin('organisations', 'products.organisation_id', 'organisations.id');
        $queryBuilder->where('products.is_main', true);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');
        $queryBuilder->where('products.variant_id', $variant->id);
        $queryBuilder->leftJoin('variants', 'variants.id', 'products.variant_id');

        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'products.id',
                'products.code',
                'products.is_variant_leader',
                'variants.slug as variant_slug',
                'products.name',
                'products.state',
                'products.price',
                'products.created_at',
                'products.updated_at',
                'products.slug',
                'products.rrp',
                'products.unit',
                'products.units',
                'products.asset_id',
                'products.available_quantity',
                'shops.name as shop_name',
                'shops.code as shop_code',
                'currencies.code as currency_code',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder->allowedSorts(['code', 'name', 'shop_code', 'units'])
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
                ->withEmptyState(
                    [
                        'title' => __("There is no products"),
                    ]
                );
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'is_variant_leader', label: ['fal', 'fa-star-half-alt'], type: 'icon')
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('Price/outer'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'rrp_per_unit', label: __('RRP/unit'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'available_quantity', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }
}
