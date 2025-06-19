<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Jun 2025 16:13:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Catalogue\IrisDropshippingLoggedInProductsInWebpageResource;
use App\Http\Resources\Catalogue\IrisDropshippingLoggedOutProductsInWebpageResource;
use App\Http\Resources\Catalogue\IrisEcomLoggedInProductsInWebpageResource;
use App\Http\Resources\Catalogue\IrisEcomLoggedOutProductsInWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

// **************************************
// Important: This code should be only called in products-1 webBlock
class GetIrisOutOfStockProductsInProductCategory extends IrisAction
{
    public function handle(ProductCategory $productCategory, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        $priceRangeFilter = AllowedFilter::callback('price_range', function ($query, $value) {
            [$min, $max] = explode(',', $value);
            $query->whereBetween('price', [(float)$min, (float)$max]);
        });

        $familyCodeFilter = AllowedFilter::callback('family', function ($query, $value) {
            $family = ProductCategory::where('code', $value)->first();
            if ($family) {
                $query->where('family_id', $family->id);
            } else {
                $query->whereRaw('0 = 1');
            }
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->leftJoin('webpages', function ($join) {
            $join->on('webpages.model_id', '=', 'products.id');
        })
            ->where('webpages.model_type', 'Product');
        $queryBuilder->where('products.is_for_sale', true);
        $queryBuilder->where('products.available_quantity', '<=', 0);

        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('department_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('family_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $queryBuilder->where('sub_department_id', $productCategory->id);
        }

        return $queryBuilder->defaultSort('available_quantity')
            ->select(
                'products.*','webpages.url'
            )
            ->allowedSorts(['price', 'created_at', 'available_quantity', 'code', 'name'])
            ->allowedFilters([$globalSearch, $priceRangeFilter, $familyCodeFilter])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        $isDropshipping = $this->shop->type == ShopTypeEnum::DROPSHIPPING;
        $isLoggedIn     = auth()->check();

        $resourceClass = match (true) {
            $isDropshipping && $isLoggedIn => IrisDropshippingLoggedInProductsInWebpageResource::class,
            $isDropshipping && !$isLoggedIn => IrisDropshippingLoggedOutProductsInWebpageResource::class,
            !$isDropshipping && $isLoggedIn => IrisEcomLoggedInProductsInWebpageResource::class,
            !$isDropshipping && !$isLoggedIn => IrisEcomLoggedOutProductsInWebpageResource::class,
        };

        return $resourceClass::collection($products);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }

}
