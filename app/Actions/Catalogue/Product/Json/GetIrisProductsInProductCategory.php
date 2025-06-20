<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductsInProductCategory extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(ProductCategory $productCategory, $inStock = true): LengthAwarePaginator
    {
        $globalSearch = $this->getGlobalSearch();

        $priceRangeFilter = $this->getPriceRangeFilter();


        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->leftJoin('webpages', function ($join) {
            $join->on('webpages.model_id', '=', 'products.id');
        })->where('webpages.model_type', 'Product');


        $queryBuilder->where('products.is_for_sale', true);
        if ($inStock) {
            $queryBuilder->where('products.available_quantity', '>', 0);
        } else {
            $queryBuilder->where('products.available_quantity', '<=', 0);
        }

        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('department_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('family_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $queryBuilder->where('sub_department_id', $productCategory->id);
        }


        return $queryBuilder->defaultSort('name')
            ->select(
                [
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
                    'webpages.url'
                ]
            )
            ->allowedSorts(['price', 'created_at', 'available_quantity', 'code', 'name'])
            ->allowedFilters([$globalSearch, $priceRangeFilter])
            ->withIrisPaginator()
            ->withQueryString();
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }

}
