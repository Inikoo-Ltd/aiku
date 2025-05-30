<?php

namespace App\Actions\Web\Website;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\Catalogue\FamilyWebsiteResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopFamily
{
    use AsObject;

    public function handle(Website $website): array
    {

        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::PRODUCT->value)->get();

        $products = $website->shop->products()->where('state', ProductStateEnum::ACTIVE)->get();
        $families = $website->shop->productCategories()->where('state', ProductCategoryStateEnum::ACTIVE)->where('type', ProductCategoryTypeEnum::FAMILY)->get();

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'families'   => FamiliesResource::collection($families),
            'products'   => ProductsResource::collection($products),
            'layout'    => Arr::get($website->unpublishedSubDepartmentSnapshot, 'layout.family', []),
            'autosaveRoute' => [
                'name'       => 'grp.models.website.autosave.family',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
        ];
    }
}
