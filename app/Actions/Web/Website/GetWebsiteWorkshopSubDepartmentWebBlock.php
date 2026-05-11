<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 16:11:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\WebsiteDepartmentsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopSubDepartmentWebBlock
{
    use AsObject;

    public function handle(Website $website): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::SUB_DEPARTMENT->value)->whereJsonContains('website_type', $website->shop->type)->get();

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'departments'     => WebsiteDepartmentsResource::collection($website->shop->departments()->where('state', ProductCategoryStateEnum::ACTIVE)),
            'layout'          => Arr::get($website->unpublishedSubDepartmentSnapshot, 'layout.sub_department', []),
            'autosaveRoute' => [
                'name'       => 'grp.models.website.autosave.sub_department',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
            'update_sub_department_route' => [
                'name' => 'grp.models.product_category.update',
                'parameters' => []
            ]
        ];
    }
}
