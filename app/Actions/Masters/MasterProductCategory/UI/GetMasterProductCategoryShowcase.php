<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\MasterProductCategoryResource;
use App\Http\Resources\Masters\MasterSubDepartmentResource;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductCategoryShowcase
{
    use AsObject;

    public function handle(MasterProductCategory $productCategory): array
    {
        return match ($productCategory->type) {
            MasterProductCategoryTypeEnum::DEPARTMENT => [
                'storeFamilyRoute' => [
                    'name' => 'grp.models.master_family.store',
                    'parameters' => [
                        'masterDepartment' => $productCategory->id
                    ]
                ],
                'department' => MasterProductCategoryResource::make($productCategory)->resolve(),
            ],
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => [
                'storeFamilyRoute' => [
                    'name' => 'grp.models.master-sub-department.master_family.store',
                    'parameters' => [
                        'masterSubDepartment' => $productCategory->id
                    ]
                ],
                'subDepartment' => MasterSubDepartmentResource::make($productCategory)->resolve(),
            ],
            default => [
                'family' => MasterProductCategoryResource::make($productCategory),
                'save_route' => [
                    'method' => 'patch',
                    'name'       => 'grp.models.master_product_categories.translations.update',
                    'parameters' => [
                        'masterProductCategory' => $productCategory->id
                    ]
                ],
            ],
        };
    }
}
