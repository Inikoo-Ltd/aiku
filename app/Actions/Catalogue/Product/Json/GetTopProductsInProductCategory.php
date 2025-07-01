<?php

/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-18h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\IrisProductsInWebpageResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class GetTopProductsInProductCategory extends OrgAction
{
    public function handle(ProductCategory $productCategory, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('products.is_main', true);
        $queryBuilder->where('products.is_for_sale', true);
        $queryBuilder->where('products.family_id', $productCategory->id)
                    ->orderBy('products.top_seller', 'desc');

        $queryBuilder
            ->defaultSort('products.top_seller', 'desc')
            ->select([
                'products.id',
                'products.image_id',
                'products.code',
                'products.name',
                'products.available_quantity',
                'products.price',
                'products.rrp',
                'products.state',
                'products.status',
                'products.created_at',
                'products.updated_at',
                'products.units',
                'products.unit',
                'products.top_seller',
                'products.web_images',
            ]);
        return $queryBuilder->allowedSorts(['code', 'name', 'top_seller'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return IrisProductsInWebpageResource::collection($products);
    }
}
