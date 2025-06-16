<?php

namespace App\Actions\Web\Website;

use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\ProductResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\Product;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopProduct
{
    use AsObject;

    public function handle(Website $website): array
    {

        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::PRODUCT->value)->get();

        $propsValue = [
            'layout' => Arr::get($website->unpublishedProductSnapshot, 'layout.product', []),
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'autosaveRoute' => [
                'name' => 'grp.models.website.autosave.product',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
            'products' => [
                'name' => 'grp.json.shop.products',
                'parameters' => [
                    'shop' => $website->shop->slug
                ]
            ]
        ];
        $updateRoute = [
            'updateRoute' => [
                'name' => 'grp.models.website.settings.update',
                'parameters' => [
                    'website' => $website->id
                ]
            ]
        ];

        return array_merge($propsValue, $updateRoute);
    }
}
