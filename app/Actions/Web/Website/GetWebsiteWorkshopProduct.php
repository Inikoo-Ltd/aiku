<?php

namespace App\Actions\Web\Website;

use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Web\WebBlockProductResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\Product;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopProduct
{
    use AsObject;

    public function handle(Website $website, Product $product): array
    {

        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::PRODUCT->value)->get();

        $layout = Arr::get($website->unpublishedProductSnapshot, 'layout.product', []);

        if ($layout) {
            data_set($layout, 'data.fieldValue.product', WebBlockProductResource::make($product)->toArray(request()));
        }

        $propsValue = [
            'layout' => $layout,
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes),
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
