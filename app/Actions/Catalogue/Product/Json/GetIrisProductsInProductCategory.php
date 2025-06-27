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
        $queryBuilder = $this->getBaseQuery($stockMode);

        $queryBuilder->select($this->getSelect());
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $queryBuilder->where('department_id', $productCategory->id);
            $parentUrl = $productCategory->url ?? '';
            $queryBuilder->selectRaw('\''.$parentUrl.'\' as parent_url');

        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $parentUrl = $productCategory->department->url ?? '';
            if ($parentUrl) {
                $parentUrl .= '/';
            }
            if ($productCategory->subDepartment && $productCategory->subDepartment->url) {
                $parentUrl .= $productCategory->subDepartment->url.'/';
            }
            $parentUrl .= $productCategory->url;

            $queryBuilder->selectRaw('\''.$parentUrl.'\' as parent_url');

            $queryBuilder->where('family_id', $productCategory->id);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $parentUrl = $productCategory->department->url ?? '';
            if ($parentUrl) {
                $parentUrl .= '/'.$productCategory->url;
            }
            $queryBuilder->selectRaw('\''.$parentUrl.'\' as parent_url');

            $queryBuilder->where('sub_department_id', $productCategory->id);
        }


        return $this->getData($queryBuilder);
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }

}
