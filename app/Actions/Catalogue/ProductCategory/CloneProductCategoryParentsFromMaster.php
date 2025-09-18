<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Sept 2025 15:09:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductCategoryParentsFromMaster
{
    use asAction;

    public function handle(ProductCategory $productCategory): ProductCategory
    {
        $masterCategory = $productCategory->masterProductCategory;
        if (!$masterCategory || $productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            return $productCategory;
        }

        if ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return $this->cloneDepartmentFromMaster($productCategory);
        }

        if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            return $this->cloneFamilyFromMaster($productCategory);
        }

        return $productCategory;
    }

    public function cloneDepartmentFromMaster(ProductCategory $productCategory): ProductCategory
    {
        $masterCategory = $productCategory->masterProductCategory;
        $masterDepartment = $masterCategory->masterDepartment;

        $department = ProductCategory::where('master_product_category_id', $masterDepartment->id)
            ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
            ->where('shop_id', $productCategory->shop_id)->first();

        if ($department) {
            UpdateSubDepartmentDepartment::run(
                $productCategory,
                [
                    'department_id' => $department->id,
                ]
            );
        }

        return $productCategory;
    }

    public function cloneFamilyFromMaster(ProductCategory $productCategory): ProductCategory
    {
        $masterCategory = $productCategory->masterProductCategory;
        $masterParent   = $masterCategory->parent;

        if ($masterParent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            $department = ProductCategory::where('master_product_category_id', $masterParent->id)
                ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
                ->where('shop_id', $productCategory->shop_id)->first();

            if ($department) {
                UpdateFamilyDepartment::run(
                    $productCategory,
                    [
                        'department_id' => $department->id,
                    ]
                );
            }
        } elseif ($masterParent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $subDepartment = ProductCategory::where('master_product_category_id', $masterParent->id)
                ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
                ->where('shop_id', $productCategory->shop_id)->first();

            if ($subDepartment) {
                UpdateFamilySubDepartment::run(
                    $productCategory,
                    [
                        'sub_department_id' => $subDepartment->id,
                    ]
                );
            }
        }


        return $productCategory;
    }


}
