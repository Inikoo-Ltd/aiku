<?php

/*
 * author Arya Permana - Kirin
 * created on 03-07-2025-11h-03m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Catalogue\Product\Json\GetIrisProductCategoriesInRelated;
use App\Http\Resources\Catalogue\ProductCategoryForRelatedWebBlockResource;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisRelatedProductCategory
{
    use AsObject;


    public function handle(array $webBlock): array
    {
        $relatedProductCategory = data_get($webBlock, 'web_block.layout.data.fieldValue.settings.product_category.*.id', []);

        // Ensure the newest data shown (for image. Arya Request)
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.settings.product_category',
            ProductCategoryForRelatedWebBlockResource::collection($relatedProductCategory ? GetIrisProductCategoriesInRelated::run($relatedProductCategory) : [])->resolve()
        );

        return [
            'type' => $webBlock['type'],
            'structure' => Arr::get(
                $webBlock,
                'web_block.layout.data.fieldValue',
                []
            ),
        ];
    }
}
