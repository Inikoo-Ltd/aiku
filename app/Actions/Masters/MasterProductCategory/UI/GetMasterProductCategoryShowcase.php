<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\IndexOpenShopsInMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Api\Dropshipping\OpenShopsInMasterShopResource;
use App\Http\Resources\Catalogue\MasterProductCategoryResource;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Http\Resources\Masters\MasterSubDepartmentsResource;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductCategoryShowcase
{
    use AsObject;

    public function handle(MasterProductCategory $productCategory): array
    {

        return match ($productCategory->type) {
            MasterProductCategoryTypeEnum::DEPARTMENT => [
                'shopsData' => OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($productCategory->masterShop, 'shops')),
                'storeFamilyRoute' => [
                    'name' => 'grp.models.master_family.store',
                    'parameters' => [
                        'masterDepartment' => $productCategory->id
                    ]
                ],
                'department' => MasterProductCategoryResource::make($productCategory)->resolve(),
                'families' => MasterProductCategoryResource::collection($productCategory->masterFamilies()),
                'translation_box' => [
                'title' => __('Multi-language Translations'),
                'save_route' => [
                     'method' => 'patch',
                     'name'       => 'grp.models.master_product_categories.translations.update',
                     'parameters' => [
                         'masterProductCategory' => $productCategory->id
                     ]
                ],
            ],
            ],
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => [
                'shopsData' => OpenShopsInMasterShopResource::collection(IndexOpenShopsInMasterShop::run($productCategory->masterShop, 'shops')),
                'storeRoute' => [
                    'name' => 'grp.models.master-sub-department.master_family.store',
                    'parameters' => [
                        'masterSubDepartment' => $productCategory->id
                    ]
                ],
                'subDepartment' => MasterSubDepartmentsResource::make($productCategory)->resolve(),
                'families' => MasterFamiliesResource::collection($productCategory->masterFamilies()),
                'translation_box' => [
                'title'      => __('Multi-language Translations'),
                'save_route' => [
                    'method' => 'patch',
                    'name'       => 'grp.models.master_product_categories.translations.update',
                    'parameters' => [
                        'masterProductCategory' => $productCategory->id
                    ]
                ],
            ],
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
