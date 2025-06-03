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
use App\Http\Resources\Catalogue\FamilyWebsiteResource;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
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

        // $webBlockTypes->each(function ($blockType) use ($website, $subDepartment) {
        //     $data = $blockType->data ?? [];
        //     $fieldValue = $data['fieldValue'] ?? [];
        //     $fieldValue['settings'] = Arr::get($website->settings, 'catalogue_template.sub_department');
        //     $fieldValue['sub_department'] = SubDepartmentsResource::make($subDepartment);
        //     $fieldValue['families'] = FamilyWebsiteResource::collection($subDepartment->children);
        //     $data['fieldValue'] = $fieldValue;
        //     $blockType->data = $data;
        // });

        $fammilies = $website->shop->productCategories()->where('state', ProductCategoryStateEnum::ACTIVE)->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->get();

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'sub_departements'   => SubDepartmentsResource::collection($fammilies),
            'layout'    => Arr::get($website->unpublishedSubDepartmentSnapshot, 'layout.sub_department', []),
            'autosaveRoute' => [
                'name'       => 'grp.models.website.autosave.sub_department',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
              'update_family_route' => [
                'name' => 'grp.models.sub-department.update',
                'parameters' => []
            ]
        ];
    }
}
