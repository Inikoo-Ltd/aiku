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
use App\Http\Resources\Catalogue\ProductWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetProductsInProductCategory extends OrgAction
{
    public function handle(ProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        if ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('department_id', $parent->id);
        } elseif ($parent->type == ProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('family_id', $parent->id);
        } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $queryBuilder->where('sub_department_id', $parent->id);
        }

        return $queryBuilder->defaultSort('-id')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductWebpageResource::collection($products);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle(parent: $productCategory);
    }

}
