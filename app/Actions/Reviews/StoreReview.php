<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 20:00:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\Catalogue\Review\Traits\HasReviewCommonLogic;
use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreReview
{
    use AsAction;
    use HasReviewCommonLogic;
    use HasReviewHydrators;

    public function handle(Product|ProductCategory|Order|Shop $reviewable, array $modelData): Review
    {
        $review = DB::transaction(function () use ($reviewable, $modelData) {
            $images     = data_get($modelData, 'images', []);
            $videos     = data_get($modelData, 'videos', []);
            $reviewData = [
                'group_id'        => $reviewable->group_id,
                'organisation_id' => $reviewable->organisation_id,
                'customer_id'     => data_get($modelData, 'customer_id'),
                'order_id'        => data_get($modelData, 'order_id'),
                'rating_main'     => $this->resolveRatingMain($modelData),
                'rating_a'        => data_get($modelData, 'rating_a'),
                'rating_b'        => data_get($modelData, 'rating_b'),
                'rating_c'        => data_get($modelData, 'rating_c'),
                'rating_d'        => data_get($modelData, 'rating_d'),
                'rating_e'        => data_get($modelData, 'rating_e'),
                'show_after'      => data_get($modelData, 'show_after'),
                'is_public'       => data_get($modelData, 'is_public', true),
                'state'           => data_get($modelData, 'state', ReviewStateEnum::WAITING_APPROVAL->value),
                'review_status'   => data_get($modelData, 'review_status', ReviewStatusEnum::PENDING->value),
                'title'           => data_get($modelData, 'title'),
                'message'         => data_get($modelData, 'message'),
                'likes'           => data_get($modelData, 'likes', 0),
                'meta'            => data_get($modelData, 'meta', []),
            ];

            if ($reviewable instanceof Order) {
                $reviewData['scope']   = ReviewScopeEnum::ORDER->value;
                $reviewData['shop_id'] = $reviewable->shop_id;
            } elseif ($reviewable instanceof Product) {
                $reviewData['scope']             = ReviewScopeEnum::PRODUCT->value;
                $reviewData['master_product_id'] = $reviewable->master_product_id;
                $reviewData['product_id']        = $reviewable->id;
                $reviewData['shop_id']           = $reviewable->shop_id;
            } elseif ($reviewable instanceof ProductCategory) {
                $reviewData['scope']                      = ReviewScopeEnum::FAMILY->value;
                $reviewData['master_product_category_id'] = $reviewable->master_product_category_id;
                $reviewData['product_category_id']        = $reviewable->id;
                $reviewData['shop_id']                    = $reviewable->shop_id;
            } elseif ($reviewable instanceof Shop) {
                $reviewData['scope']   = ReviewScopeEnum::SHOP->value;
                $reviewData['shop_id'] = $reviewable->id;
            }

            $review = Review::create($reviewData);

            if (!empty($images)) {
                $this->storeUploadedImages($review, $images);
            }

            if (!empty($videos)) {
                $this->storeUploadedVideos($review, $videos);
            }

            return $review->refresh();
        });

        $this->reviewHydrators($review);

        return $review;
    }

    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'reviewable_type' => ['required', Rule::in(['Order', 'Product', 'ProductCategory', 'Shop'])],
            'reviewable_id'   => ['required', 'integer', 'min:1'],
            'rating'          => ['required', 'numeric', 'min:1', 'max:5'],
            'order_id'        => ['nullable', 'integer', 'exists:orders,id'],
        ]);
    }
}
