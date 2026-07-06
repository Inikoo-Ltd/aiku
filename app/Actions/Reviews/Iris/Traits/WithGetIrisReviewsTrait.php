<?php

namespace App\Actions\Reviews\Iris\Traits;

use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\Review;
use App\Services\QueryBuilder;
use Illuminate\Support\Arr;

trait WithGetIrisReviewsTrait
{
    public function getBaseQuery(Product|ProductCategory|Shop $parent): QueryBuilder
    {
        $scopes = [];
        if ($parent instanceof Shop) {
            $shop = $parent;
            $scopes = [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER];
        } else {
            $shop = $parent->shop;

            if ($parent instanceof Product) {
                $scopes = [ReviewScopeEnum::PRODUCT];
            } elseif ($parent instanceof ProductCategory) {
                $scopes = [ReviewScopeEnum::FAMILY];
            }
        }

        $setting = Arr::get($shop->settings, 'reviews.validation_scope.product', []);
        $minRating = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);

        $queryBuilder = QueryBuilder::for(Review::class);

        if (Arr::get($setting, 'enabled', false)) {
            if (Arr::get($setting, 'scope') == 'group') {
                $queryBuilder->where('reviews.group_id', $parent->group_id);
            } else {
                $queryBuilder->where('reviews.organisation_id', $parent->organisation_id);
            }

            if ($parent instanceof Product) {
                $queryBuilder->where('reviews.master_product_id', $parent->master_product_id);
            } elseif ($parent instanceof ProductCategory) {
                $queryBuilder->where('reviews.master_product_category_id', $parent->master_product_category_id);
            }
        } else {
            $queryBuilder->where('reviews.shop_id', $shop->id);

            if ($parent instanceof Product) {
                $queryBuilder->where('reviews.product_id', $parent->id);
            } elseif ($parent instanceof ProductCategory) {
                $queryBuilder->where('reviews.product_category_id', $parent->id);
            }
        }

        return $queryBuilder->where('reviews.rating_main', '>=', $minRating)
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED)
            ->whereIn('reviews.scope', $scopes);
    }
}
