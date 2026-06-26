<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 20:00:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\Catalogue\Review\Traits\HasReviewCommonLogic;
use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Actions\Comms\Email\SendNewReviewEmailToSubscribers;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreReview extends OrgAction
{

    use HasReviewCommonLogic;
    use HasReviewHydrators;


    /**
     * @throws \Throwable
     */
    public function action(Product|ProductCategory|Order|Shop $reviewable, array $modelData): Review
    {
        if ($reviewable instanceof Shop) {
            $shop = $reviewable;
        } else {
            $shop = $reviewable->shop;
        }
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($reviewable, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function handle(Product|ProductCategory|Order|Shop $reviewable, array $modelData): Review
    {
        $review = DB::transaction(function () use ($reviewable, $modelData) {
            $images = data_get($modelData, 'images', []);
            $videos = data_get($modelData, 'videos', []);


            $reviewData = [
                'group_id'        => $reviewable->group_id,
                'organisation_id' => $reviewable->organisation_id,
                'customer_id'     => data_get($modelData, 'customer_id'),
                'order_id'        => data_get($modelData, 'order_id'),
                'shop_id'         => $this->shop->id,
                'rating_main'     => $this->resolveRatingMain($modelData),
                'rating_a'        => data_get($modelData, 'rating_a'),
                'rating_b'        => data_get($modelData, 'rating_b'),
                'rating_c'        => data_get($modelData, 'rating_c'),
                'rating_d'        => data_get($modelData, 'rating_d'),
                'rating_e'        => data_get($modelData, 'rating_e'),
                'is_public'       => data_get($modelData, 'is_public', true),
                'message'         => data_get($modelData, 'message'),
                'likes'           => data_get($modelData, 'likes', 0),
                'meta'            => data_get($modelData, 'meta', []),
                'language_id'     => data_get($modelData, 'language_id', $this->shop->language->id),
                'external_id'     => data_get($modelData, 'external_id'),
                ...$this->resolveScopeData($reviewable),
                ...$this->resolvePublishingData($this->shop, $modelData),
            ];

            $review = Review::create($reviewData);

            if (!empty($images)) {
                $this->storeUploadedImages($review, $images);
            }

            if (!empty($videos)) {
                $this->storeUploadedVideos($review, $videos);
            }
            SendNewReviewEmailToSubscribers::dispatch($review->id);
            return $review->refresh();
        });

        $this->reviewHydrators($review);

        return $review;
    }


    private function resolveScopeData(Product|ProductCategory|Order|Shop $reviewable): array
    {
        if ($reviewable instanceof Order) {
            return [
                'scope' => ReviewScopeEnum::ORDER,
            ];
        }

        if ($reviewable instanceof Product) {
            return [
                'scope'             => ReviewScopeEnum::PRODUCT,
                'master_product_id' => $reviewable->master_product_id,
                'product_id'        => $reviewable->id,
            ];
        }

        if ($reviewable instanceof ProductCategory) {
            return [
                'scope'                      => ReviewScopeEnum::FAMILY,
                'master_product_category_id' => $reviewable->master_product_category_id,
                'product_category_id'        => $reviewable->id,
            ];
        }

        return [
            'scope' => ReviewScopeEnum::SHOP,
        ];
    }

    private function resolvePublishingData(?Shop $shop, array $modelData): array
    {
        $settings         = $shop?->settings ?? [];
        $approvalRequired = (bool)Arr::get($settings, 'reviews.data.approval_required', false);
        $isPublic         = (bool)data_get($modelData, 'is_public', true);

        if (!$isPublic) {
            return [
                'state'         => ReviewStateEnum::PRIVATE,
                'review_status' => ReviewStatusEnum::NA,
                'approved'      => false,
                'auto_approved' => false,
            ];
        }

        if ($approvalRequired) {
            return [
                'state'         => ReviewStateEnum::WAITING_APPROVAL,
                'review_status' => ReviewStatusEnum::PENDING,
            ];
        }

        $isDelay    = Arr::get($settings, 'reviews.auto_publishing.delay', true);
        $delayHours = (int)Arr::get($settings, 'reviews.auto_publishing.delay_hours', 24);


        if ($isDelay) {
            return [
                'state'           => ReviewStateEnum::WAITING_APPROVAL,
                'review_status'   => ReviewStatusEnum::PENDING,
                'auto_approve_at' => now()->addHours($delayHours),
            ];
        } else {
            return [
                'state'         => ReviewStateEnum::PUBLISHED,
                'review_status' => ReviewStatusEnum::APPROVED,
                'approved'      => true,
                'auto_approved' => true,
                'published_at'  => now(),
            ];
        }
    }

    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'rating'   => ['required', 'numeric', 'min:1', 'max:5'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);
    }
}
