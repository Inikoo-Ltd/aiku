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
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductCategoryParentsFromMaster
{
    use asAction;

    public function handle(ProductCategory $productCategory, Command|null $command = null): ProductCategory
    {
        $masterCategory = $productCategory->masterProductCategory;
        if (!$masterCategory || $productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            return $productCategory;
        }

        if ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return $this->cloneSubDepartmentParentsFromMaster($productCategory, $command);
        }

        if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            return $this->cloneFamilyParentsFromMaster($productCategory);
        }

        return $productCategory;
    }

    public function cloneSubDepartmentParentsFromMaster(ProductCategory $productCategory, Command|null $command = null): ProductCategory
    {
        $masterCategory = $productCategory->masterProductCategory;
        $masterDepartment = $masterCategory->masterDepartment;
        if (!$masterDepartment) {
            $command?->error('master department not found');
            return $productCategory;
        }

        $department = ProductCategory::where('master_product_category_id', $masterDepartment->id)
            ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
            ->where('shop_id', $productCategory->shop_id)->first();

        if ($department) {
            $command?->info('set department to '.$department->slug);
            UpdateSubDepartmentDepartment::run(
                $productCategory,
                [
                    'department_id' => $department->id,
                ]
            );
        } else {
            $command?->error('department not found');
        }

        return $productCategory;
    }

    public function cloneFamilyParentsFromMaster(ProductCategory $productCategory): ProductCategory
    {
        $masterCategory = $productCategory->masterProductCategory;
        $masterParent   = $masterCategory->parent;
        if (!$masterParent) {
            return $productCategory;
        }

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

    public function getCommandSignature(): string
    {
        return 'category:get_parents_from_master {product_category}';
    }

    public function asCommand(Command $command): int
    {
        $productCategory = ProductCategory::where('slug', $command->argument('product_category'))->firstOrFail();
        $command->info('Updating parents of '.$productCategory->name);
        $this->handle($productCategory, $command);

        return 0;
    }


}
