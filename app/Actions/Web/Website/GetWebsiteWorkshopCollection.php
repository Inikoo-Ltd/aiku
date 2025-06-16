<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-15h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Website;

use App\Actions\Web\Webpage\Json\GetWebpagesWithCollection;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\WebsiteDepartmentsResource;
use App\Http\Resources\Catalogue\WorkshopSubDepartmentsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Http\Resources\Web\WebpagesResource;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopCollection
{
    use AsObject;

    public function handle(Website $website): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::COLLECTION->value)->get();

        $departments = $website->shop->productCategories()
            ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
            ->where('state', ProductCategoryStateEnum::ACTIVE)
            ->has('collections')
            ->with('collections')
            ->get();
        $subDepartments = $website->shop->productCategories()
            ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->where('state', ProductCategoryStateEnum::ACTIVE)
            ->has('collections')
            ->with('collections')
            ->get();

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'departments'   => WebsiteDepartmentsResource::collection($departments),
            'subDepartments'   => WorkshopSubDepartmentsResource::collection($subDepartments),
            'layout'    => Arr::get($website->unpublishedCollectionSnapshot, 'layout.collection', []),
            'webpages'  => WebpagesResource::collection(GetWebpagesWithCollection::run($website)),
            'autosaveRoute' => [
                'name'       => 'grp.models.website.autosave.collection',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
        ];
    }
}
