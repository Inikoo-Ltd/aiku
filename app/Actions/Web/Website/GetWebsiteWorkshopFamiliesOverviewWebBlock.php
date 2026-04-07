<?php

/*
 * author Louis Perez
 * created on 07-04-2026-13h-04m
 * github: https://github.com/louis-perez
 * copyright 2026
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

class GetWebsiteWorkshopFamiliesOverviewWebBlock
{
    use AsObject;

    public function handle(Website $website, $type = null): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILIES_OVERVIEW->value)->whereJsonContains('website_type', $website->shop->type)->get();

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'departments'   => WebsiteDepartmentsResource::collection($website->shop->departments()->where('state', ProductCategoryStateEnum::ACTIVE)),
            'layout'    => Arr::get($website->unpublishedFamiliesOverviewSnapshot, 'layout.families_overview', []),
            'autosaveRoute' => [
                'name'       => 'grp.models.website.autosave.families_overview',
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
