<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Catalogue\Product\Json\GetIrisProductsInRecommendation;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use Illuminate\Support\Arr;

class GetIrisWebBlockRecommendationsFromMaster
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.recommendation_settings',
            data_get($webpage, 'website.settings.recommender_web_block', [])
        );


        $recommendedProducts = collect();
        if ($webpage->model instanceof ProductCategory) {
            $recommendedProducts = IrisAuthenticatedProductsInWebpageResource::collection(
                GetIrisProductsInRecommendation::run($webpage->model)
            )->resolve();
        }

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.products_recommended',
            $recommendedProducts
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
