<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Catalogue\Product\Json\GetIrisProductsInRecommendation;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;

class GetWebBlockRecommendationsFromMaster
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        data_set(
            $webBlock,
            'web_block.layout.data.permissions',
            ['edit', 'hidden']
        );

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.recommendation_settings',
            data_get($webpage, 'website.settings.recommender_web_block', [])
        );

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.products_recommended',
            IrisAuthenticatedProductsInWebpageResource::collection(
                GetIrisProductsInRecommendation::run($webpage->model)
            )->resolve()
        );

        return $webBlock;
    }
}
