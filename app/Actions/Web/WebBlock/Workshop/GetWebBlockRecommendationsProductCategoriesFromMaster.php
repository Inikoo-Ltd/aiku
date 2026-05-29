<?php

/*
 * author Louis Perez
 * created on 29-05-2026-13h-59m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Catalogue\Product\Json\GetIrisProductCategoriesInRecommendation;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Web\WebBlockFamiliesResource;

class GetWebBlockRecommendationsProductCategoriesFromMaster
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $permissions =  [];

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.recommendation_settings',
            data_get($webpage, 'website.settings.recommender_product_category_web_block', [])
        );

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.product_category_recommended',
            WebBlockFamiliesResource::collection(
                GetIrisProductCategoriesInRecommendation::run($webpage->model)
            )->resolve()
        );

         data_set($webBlock, 'web_block.layout.data.permissions', $permissions);

        return $webBlock;
    }
}
