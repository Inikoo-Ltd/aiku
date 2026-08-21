<?php

namespace App\Actions\Web\Website;

use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Web\WebBlockFamilyResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Http\Resources\Web\WebpageProductWorkshopResource;
use App\Models\Catalogue\Product;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopProduct
{
    use AsObject;
    use HasWebBlockLayoutData;

    public function handle(Website $website, Product $product): array
    {
        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::PRODUCT->value)->whereJsonContains('website_type', $website->shop->type)->get();

        $layout = Arr::get($website->unpublishedProductSnapshot, 'layout.product', []);

        if ($layout) {
            data_set($layout, 'data.fieldValue.product', WebpageProductWorkshopResource::make($product)->toArray(request()));
            data_set($layout, 'data.fieldValue.tabs', [
                'description' => $product->description,
                ...($product->family ? WebBlockFamilyResource::getTabsData($product->family) : [])
            ]);
            data_set($layout, 'data.fieldValue.tabs_style', $this->getFamilyExtraDescriptionLayoutData($website->published_layout));
        }

        $propsValue = [
            'layout' => $layout,
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
            'type_shop' => $website->shop->type,
            'autosaveRoute' => [
                'name' => 'grp.models.website.autosave.product',
                'parameters' => [
                    'website' => $website->id
                ]
            ],
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
