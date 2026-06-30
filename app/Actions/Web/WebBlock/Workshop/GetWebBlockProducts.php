<?php

/*
 * author Louis Perez
 * created on 28-05-2026-14h-27m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Catalogue\Product\Json\GetIrisProductsInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInProductCategory;
use App\Actions\Catalogue\Review\UI\IndexReviewsInIris;
use App\Http\Resources\Catalogue\IrisProductsInWebpageForWorkshopResource;
use App\Http\Resources\Catalogue\ReviewsInIrisResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockProducts
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        /** @var Collection|ProductCategory $model */
        $model = $webpage->model;

        if ($webpage->model_type == 'Collection') {
            $products           = IrisProductsInWebpageForWorkshopResource::collection(GetIrisProductsInCollection::run(collection: $model, stockMode: 'in_stock'));
            $productsOutOfStock = IrisProductsInWebpageForWorkshopResource::collection(GetIrisProductsInCollection::run(collection: $model, stockMode: 'out_of_stock'));
        } else {
            $products           = IrisProductsInWebpageForWorkshopResource::collection(GetIrisProductsInProductCategory::run(productCategory: $model, stockMode: 'in_stock'));
            $productsOutOfStock = IrisProductsInWebpageForWorkshopResource::collection(GetIrisProductsInProductCategory::run(productCategory: $model, stockMode: 'out_of_stock'));
        }

        $reviews = IndexReviewsInIris::run(parent: $webpage->model, prefix: $webpage->title);
        $avgReview = IndexReviewsInIris::make()->avgReview($webpage->model);

        $review = [
            'reviews'                           => ReviewsInIrisResource::collection($reviews),
            'review_summary'                    => $avgReview ?? 0,
            'allow_review_reaction'             => data_get($webpage->shop->settings, 'reviews.allow_reactions', true),
            'allow_review_reply_reaction'       => data_get($webpage->shop->settings, 'reviews.allow_reactions', true),
            'minimum_reviews_to_show'           => data_get($webpage->shop->settings, 'reviews.minimum_reviews_to_show', 0),    
        ];

        $permissions = ['edit'];
        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue.reviews', $review);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products', $products);
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_type', $webpage->sub_type);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_type', $webpage->model_type);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_id', $webpage->model_id);
        data_set($webBlock, 'web_block.layout.data.fieldValue.model_slug', $model?->slug);
        data_set($webBlock, 'web_block.layout.data.fieldValue.products_out_of_stock', $productsOutOfStock);

        return $webBlock;
    }
}
