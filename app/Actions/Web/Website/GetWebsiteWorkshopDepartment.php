<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 16:11:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\DepartmentWebsiteResource;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopDepartment
{
    use AsObject;

    public function handle(Website $website, ProductCategory $department): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::SUB_DEPARTMENT->value)->get();

        $webBlockTypes->each(function ($blockType) use ($website, $department) {
            $data = $blockType->data ?? [];
            $fieldValue = $data['fieldValue'] ?? [];
            $fieldValue['settings'] = Arr::get($website->settings, 'catalogue_template.department');
            $fieldValue['department'] = DepartmentWebsiteResource::make($department);
            $fieldValue['sub_departments'] = SubDepartmentsResource::collection($department->children);
            $data['fieldValue'] = $fieldValue;
            $blockType->data = $data;
        });

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'departments'   => DepartmentsResource::collection($website->shop->departments()->where('state', ProductCategoryStateEnum::ACTIVE)),
            'layout'       => $website->unpublishedDepartmentSnapshot->layout ?? [],
            'autosaveRoute' => [
                'name'       => 'grp.models.website.autosave.department',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
        ];
    }
}
