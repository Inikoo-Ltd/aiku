<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Catalogue\Product\Json\GetIrisProductCategoriesInRecommendation;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use Illuminate\Support\Arr;

class GetIrisWebBlockRecommendationsProductCategoriesFromMaster
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.recommendation_settings',
            data_get($webpage, 'website.settings.recommender_web_block', [])
        );

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.product_category_recommended',
            WebBlockFamiliesResource::collection(
                GetIrisProductCategoriesInRecommendation::run($webpage->model)
            )->resolve()
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
