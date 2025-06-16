<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\IrisProductsInWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetIrisProductsInProductCategory extends OrgAction
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
            $query->whereBetween('price', [(float) $min, (float) $max]);
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
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('department_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('family_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $queryBuilder->where('sub_department_id', $productCategory->id);
        }

        return $queryBuilder->defaultSort('-id')
            ->allowedSorts(['price', 'created_at'])
            ->allowedFilters([$globalSearch, $priceRangeFilter, $familyCodeFilter])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return IrisProductsInWebpageResource::collection($products);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle(productCategory: $productCategory);
    }

}
