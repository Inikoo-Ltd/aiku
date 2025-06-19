<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Catalogue\IrisDropshippingLoggedInProductsInWebpageResource;
use App\Http\Resources\Catalogue\IrisDropshippingLoggedOutProductsInWebpageResource;
use App\Http\Resources\Catalogue\IrisEcomLoggedInProductsInWebpageResource;
use App\Http\Resources\Catalogue\IrisEcomLoggedOutProductsInWebpageResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

// **************************************
// Important: This code should be only called in products-1 webBlock
class GetIrisOutOfStockProductsInCollection extends IrisAction
{
    public function handle(Collection $collection, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('products.is_for_sale', true);
        $queryBuilder->where('products.available_quantity', '<=', 0);

        $queryBuilder->join('model_has_collections', function ($join) use ($collection) {
            $join->on('products.id', '=', 'model_has_collections.model_id')
                ->where('model_has_collections.model_type', '=', 'Product')
                ->where('model_has_collections.collection_id', '=', $collection->id);
        });


        return $queryBuilder->defaultSort('available_quantity')
            ->select(
                'products.*',
            )
            ->allowedSorts(['price', 'created_at','available_quantity','code','name'])
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

    public function asController(Collection $collection, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(collection: $collection);
    }

}
