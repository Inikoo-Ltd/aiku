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
                'department' => MasterProductCategoryResource::make($productCategory)->resolve(),
                'families' => MasterProductCategoryResource::collection($productCategory->masterFamilies()),
                'translation_box' => [
                'title' => __('Multi-language Translations'),
                'save_route' => [
                     'name'       => 'grp.models.master_product_categories.translations.update',
                     'parameters' => []
                ],
            ],
            ],
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => [
                'subDepartment' => MasterSubDepartmentsResource::make($productCategory)->resolve(),
                'families' => MasterFamiliesResource::collection($productCategory->masterFamilies()),
                'translation_box' => [
                'title'      => __('Multi-language Translations'),
                'save_route' => [
                    'name'       => 'grp.models.master_product_categories.translations.update',
                    'parameters' => []
                ],
            ],
            ],
            default => [
                'family' => MasterProductCategoryResource::make($productCategory),
                'save_route' => [
                    'name'       => 'grp.models.master_product_categories.translations.update',
                    'parameters' => []
                ],
            ],
        };
    }
}
