<?php

namespace App\Actions\Web\Website;

use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Catalogue\IrisProductsInWebpageResource;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInProductCategory;

class GetWebsiteWorkshopFamily
{
    use AsObject;

    public function handle(Website $website): array
    {
        $family = $website->shop->productCategories()
            ->where('state', ProductCategoryStateEnum::ACTIVE)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->first();

        if (!$family) {
            return [
                'web_block_types' => [],
                'products' => [],
                'layout' => [],
                'autosaveRoute' => null,
            ];
        }

        $webBlockTypes = WebBlockType::query()
            ->where('category', WebBlockCategoryScopeEnum::LIST_PRODUCTS->value)
            ->whereJsonContains('website_type', $website->shop->type)
            ->get();

        $products = IrisProductsInWebpageResource::collection(
            GetIrisProductsInProductCategory::run(productCategory: $family, stockMode: 'all', topSeller: false)
        );

        $topSeller = IrisProductsInWebpageResource::collection(
            GetIrisProductsInProductCategory::run(productCategory: $family, stockMode: 'all', topSeller: true)
        );

        return [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'products' => $products,
            'top_seller' => $topSeller,
            'family' =>  $family,
            'layout' => Arr::get($website->unpublishedFamilySnapshot, 'layout.family', []),
            'autosaveRoute' => [
                'name' => 'grp.models.website.autosave.family',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
        ];
    }
}
