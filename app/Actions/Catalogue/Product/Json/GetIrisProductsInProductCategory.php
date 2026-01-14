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
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductsInProductCategory extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(ProductCategory $productCategory, $stockMode = 'all', bool $topSeller = false): LengthAwarePaginator
    {
        $queryBuilder = $this->getBaseQuery($stockMode, $topSeller);
        $queryBuilder->whereNotNull('products.webpage_id');
        $queryBuilder
            ->where(function ($query) {
                $query
                    ->whereNull('products.variant_id')
                    ->orWhere('products.is_variant_leader', true);
            });
        $queryBuilder->select(
            $this->getSelect([
                DB::raw('products.variant_id IS NOT NULL as is_variant'),
                DB::raw('exists (
                        select os.is_on_demand
                        from org_stocks os
                        join product_has_org_stocks phos on phos.org_stock_id = os.id
                        where phos.product_id = products.id
                        and os.is_on_demand = true
                    ) as is_on_demand')
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
