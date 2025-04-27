<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Sept 2024 17:47:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateSubDepartments;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateSubDepartments;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDepartments;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateFamilies;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSubDepartments;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateDepartments;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateFamilies;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDepartments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateFamilies;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSubDepartments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDepartments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateFamilies;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSubDepartments;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;

trait WithProductCategoryHydrators
{
    protected function productCategoryHydrators(ProductCategory $productCategory): void
    {
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            GroupHydrateDepartments::dispatch($productCategory->group)->delay($this->hydratorsDelay);
            OrganisationHydrateDepartments::dispatch($productCategory->organisation)->delay($this->hydratorsDelay);
            ShopHydrateDepartments::dispatch($productCategory->shop)->delay($this->hydratorsDelay);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            GroupHydrateFamilies::dispatch($productCategory->group)->delay($this->hydratorsDelay);
            OrganisationHydrateFamilies::dispatch($productCategory->organisation)->delay($this->hydratorsDelay);
            ShopHydrateFamilies::dispatch($productCategory->shop)->delay($this->hydratorsDelay);

            if ($productCategory->parent_id) {
                ProductCategoryHydrateFamilies::dispatch($productCategory->parent)->delay($this->hydratorsDelay);
            }
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            GroupHydrateSubDepartments::dispatch($productCategory->group)->delay($this->hydratorsDelay);
            OrganisationHydrateSubDepartments::dispatch($productCategory->organisation)->delay($this->hydratorsDelay);
            ShopHydrateSubDepartments::dispatch($productCategory->shop)->delay($this->hydratorsDelay);

            if ($productCategory->department_id) {
                DepartmentHydrateSubDepartments::dispatch($productCategory->department)->delay($this->hydratorsDelay);
            }
            if ($productCategory->sub_department_id) {
                SubDepartmentHydrateSubDepartments::dispatch($productCategory->subDepartment)->delay($this->hydratorsDelay);
            }
        }
    }

    public function masterProductCategoryUsageHydrators(ProductCategory $productCategory, ?MasterProductCategory $masterProductCategory): void
    {
        if (!$masterProductCategory) {
            return;
        }

        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            MasterDepartmentHydrateDepartments::dispatch($masterProductCategory)->delay($this->hydratorsDelay);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            MasterFamilyHydrateFamilies::dispatch($masterProductCategory)->delay($this->hydratorsDelay);
        }
    }

}
