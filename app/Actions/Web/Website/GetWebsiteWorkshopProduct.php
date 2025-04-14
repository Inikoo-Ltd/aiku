<?php

namespace App\Actions\Web\Website;

use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\ProductResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Catalogue\Product;
use App\Models\Web\WebBlockType;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsiteWorkshopProduct
{
    use AsObject;

    public function handle(Website $website, Product $product): array
    {

        $webBlockTypes = WebBlockType::where('category', WebBlockCategoryScopeEnum::PRODUCT->value)->get();

        $webBlockTypes->each(function ($blockType) use ($product) {
            $data = $blockType->data ?? [];
            $fieldValue = $data['fieldValue'] ?? [];

            $fieldValue['product'] = ProductResource::make($product);
            $data['fieldValue'] = $fieldValue;
            $blockType->data = $data;
        });

        $propsValue = [
            'web_block_types' => WebBlockTypesResource::collection($webBlockTypes)
        ];
        $updateRoute = [
            'updateRoute' => [
                'name'       => 'grp.models.website.settings.update',
                'parameters' => [
                    'website' => $website->id
                ]
            ]
                ];

        return array_merge($propsValue, $updateRoute);
    }
}
