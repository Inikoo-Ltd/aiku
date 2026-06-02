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
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisRelatedProductCategory
{
    use AsObject;


    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.recommendation_settings',
            data_get($webpage, 'website.settings.recommender_product_category_web_block', [])
        );

        $relatedProductCategory = Arr::get($webBlock, 'web_block.layout.data.fieldValue.settings.product_category', []);

        // Ensure newest data shown (for image. Arya Request)
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.settings.product_category',
            ProductCategoryForRelatedWebBlockResource::collection($relatedProductCategory ? GetIrisProductCategoriesInRelated::run() : [])->resolve(),
            true
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
