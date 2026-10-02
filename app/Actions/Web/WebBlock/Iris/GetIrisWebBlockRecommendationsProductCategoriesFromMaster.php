<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Catalogue\Product\Json\GetIrisProductCategoriesInRecommendation;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Media;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use Illuminate\Support\Arr;

class GetIrisWebBlockRecommendationsProductCategoriesFromMaster
{
    use AsObject;
    use WithIrisImageVariants;

    public const array SRCSET_WIDTHS = [360, 720, 1440];

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        if (!$webpage->model instanceof ProductCategory) {
            return null;
        }

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.recommendation_settings',
            data_get($webpage, 'website.settings.recommender_product_category_web_block', [])
        );

        $recommendedProductCategories = GetIrisProductCategoriesInRecommendation::run($webpage->model)->values();

        $mainImagesById = Media::whereIn('id', $recommendedProductCategories->pluck('image_id')->filter())
            ->get()
            ->keyBy('id');

        $recommendedProductCategoriesData = WebBlockFamiliesResource::collection($recommendedProductCategories)->resolve();

        foreach ($recommendedProductCategories as $index => $recommendedProductCategory) {
            $mainImage = $mainImagesById->get($recommendedProductCategory->image_id);

            $recommendedProductCategoriesData[$index]['srcset'] = $mainImage
                ? $this->getWidthSrcSets($mainImage, self::SRCSET_WIDTHS)
                : null;
        }

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.product_category_recommended',
            $recommendedProductCategoriesData
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
