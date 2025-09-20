<?php

/*
 * author Arya Permana - Kirin
 * created on 27-05-2025-15h-38m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Website;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\WorkshopSubDepartmentsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopSubDepartment
{
    use AsObject;

    public function handle(Website $website): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILY->value)->get();

        $families = $website->shop->productCategories()->where('state', ProductCategoryStateEnum::ACTIVE)->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->get();

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'sub_departments'   => WorkshopSubDepartmentsResource::collection($families),
            'layout'    => Arr::get($website->unpublishedSubDepartmentSnapshot, 'layout.sub_department', []),
            'autosaveRoute' => [
                'name'       => 'grp.models.website.autosave.sub_department',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
            'update_family_route' => [
                'name' => 'grp.models.product_category.update',
                'parameters' => []
            ]
        ];
    }
}
