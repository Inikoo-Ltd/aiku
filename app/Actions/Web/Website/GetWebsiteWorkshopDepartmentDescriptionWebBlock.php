<?php

/*
 * author Louis Perez
 * created on 09-06-2026-10h-24m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\Website;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\WorkshopDepartmentsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopDepartmentDescriptionWebBlock
{
    use AsObject;

    public function handle(Website $website, $type = null): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::DEPARTMENT_DESCRIPTION->value)
                ->whereNot('name', 'family-extra-description')
                ->whereJsonContains('website_type', $website->shop->type)
                ->get();

        return [
            'web_block_types'   => WebBlockTypesResource::collection($webBlockTypes),
            'department'        => WorkshopDepartmentsResource::collection($website->shop->getDepartmentsRelation()->where('state', ProductCategoryStateEnum::ACTIVE)->get()),
            'layout'            => Arr::get($website->unpublishedDepartmentDescriptionSnapshot, 'layout', []),
            'autosaveRoute' => [
                'name'       => 'grp.models.website.autosave.department_description',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
            'route_get_list' => [
                'name' => 'grp.org.shops.show.catalogue.departments.show.sub_departments.index',
                'parameters' => [
                    'shop' => $website->shop->slug,
                    'organisation' => $website->organisation->slug
                ]
            ]
        ];
    }
}
