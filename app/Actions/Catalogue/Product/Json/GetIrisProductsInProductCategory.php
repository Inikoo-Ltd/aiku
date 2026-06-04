<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductsInProductCategory extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(ProductCategory $productCategory, $stockMode = 'all', bool $topSeller = false): LengthAwarePaginator
    {
        $queryBuilder = $this->getBaseQuery($stockMode, $topSeller);

        $queryBuilder
            ->where(function ($query) {
                $query
                    ->whereNull('products.variant_id')
                    ->orWhere('products.is_variant_leader', true);
            });
        $queryBuilder->select(
            $this->getSelect([
                DB::raw('products.variant_id IS NOT NULL as is_variant')
            ])
        );
        $perPage = null;
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('products.department_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $queryBuilder->where('products.family_id', $productCategory->id);
            $perPage = 250;
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $queryBuilder->where('products.sub_department_id', $productCategory->id);
        }

        // Section: Sort
        $orderBy = request()->query('sort');

        if (!$orderBy) {
            $orderBy = $productCategory->type === ProductCategoryTypeEnum::FAMILY ? 'recommended' : 'created_at';
        }

        if ($orderBy == 'recommended') {
            if ($productCategory->type === ProductCategoryTypeEnum::FAMILY) {
                $queryBuilder->orderBy("index_under_{$productCategory->type->value}");
            }
            $queryBuilder->orderBy("name");

            return $this->getUnsortedData($queryBuilder, $perPage);
        } else {
            if (str_starts_with($orderBy, '-')) {
                $column    = ltrim($orderBy, '-');
                $direction = 'desc';
            } else {
                $column    = $orderBy;
                $direction = 'asc';
            }

            $allowedColumnsToOrder = ['name', 'rrp', 'price', 'code', 'created_at'];
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
