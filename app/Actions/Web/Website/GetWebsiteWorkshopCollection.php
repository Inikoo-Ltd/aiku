<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-15h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/
namespace App\Actions\Web\Website;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\DepartmentWebsiteResource;
use App\Http\Resources\Catalogue\SubDepartmentsResource;
use App\Http\Resources\Catalogue\WebsiteDepartmentsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
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

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'layout'    => Arr::get($website->unpublishedCollectionSnapshot, 'layout.collection', []),
            'autosaveRoute' => [
                'name'       => 'grp.models.website.autosave.collection',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
        ];
    }
}
