<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Jun 2025 14:43:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\IrisAjaxAuthenticatedProductsInWebpageResource;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetIrisInStockProductsInProductCategory extends IrisAction
{
    use WithIrisProductsInWebpage;



    public function handle(ProductCategory $productCategory): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id');

        $queryBuilder->where('products.available_quantity', '>', 0);
        //todo
        //$queryBuilder->where('products.has_live_webpage', true);


        $perPage = null;
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('products.department_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('products.family_id', $productCategory->id);
            $perPage = 250;
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $queryBuilder->where('products.sub_department_id', $productCategory->id);
        }

        $queryBuilder->select(
            [
                'products.id',
                'products.name',
                'products.code',
                'products.web_images',
                'products.rrp',
                'products.price',
                'products.units',
                'products.unit',
                'webpages.canonical_url',
                'products.available_quantity',
                'products.top_seller',
                'products.state',
                'products.status',

            ]
        );


        // Section: Sort
        $orderBy = request()->query('order_by');
        if ($orderBy) {
            if (str_starts_with($orderBy, '-')) {
                $column    = ltrim($orderBy, '-');
                $direction = 'desc';
            } else {
                $column    = $orderBy;
                $direction = 'asc';
            }

            $allowedColumnsToOrder = ['name', 'rrp', 'price', 'code'];
            if (in_array($column, $allowedColumnsToOrder)) {
                $queryBuilder->orderBy($column, $direction);
            }
        }

        return $this->getData($queryBuilder, $perPage);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return IrisAjaxAuthenticatedProductsInWebpageResource::collection($products);
    }

}
