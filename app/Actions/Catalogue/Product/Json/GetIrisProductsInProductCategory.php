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
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductsInProductCategory extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(ProductCategory $productCategory, $stockMode = 'all'): LengthAwarePaginator
    {
        $customer     = request()->user()?->customer;
        $queryBuilder = $this->getBaseQuery($stockMode);

        $queryBuilder->select($this->getSelect());
        $perPage = null;
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('department_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('family_id', $productCategory->id);
            $perPage = 250;
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $queryBuilder->where('sub_department_id', $productCategory->id);
        }
        $baseUrl = $productCategory->url ?? '';
        $queryBuilder->selectRaw('\''.$baseUrl.'\' as parent_url');

        $groupByColumns = ['products.id', 'webpages.id', 'webpages.url'];
        if ($customer) {
            $basket = $customer->orderInBasket;
            if ($basket) {
                $groupByColumns[] = 'transactions.id';
            }
        }
        $queryBuilder->groupBy($groupByColumns);

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


}
