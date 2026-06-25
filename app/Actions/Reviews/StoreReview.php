<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 20:00:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\Catalogue\Review\Traits\HasReviewCommonLogic;
use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Enums\Catalogue\Review\ReviewAutoPublishingEnum;
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
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreReview
{
    use AsAction;
    use HasReviewCommonLogic;
    use HasReviewHydrators;

    protected int $hydratorsDelay = 0;

    public function handle(Product|ProductCategory|Order|Shop $reviewable, array $modelData): Review
    {
        $review = DB::transaction(function () use ($reviewable, $modelData) {
            $images = data_get($modelData, 'images', []);
            $videos = data_get($modelData, 'videos', []);

            $shop = $this->resolveShop($reviewable);

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
                'is_public'       => data_get($modelData, 'is_public', true),
                'title'           => data_get($modelData, 'title'),
                'message'         => data_get($modelData, 'message'),
                'likes'           => data_get($modelData, 'likes', 0),
                'meta'            => data_get($modelData, 'meta', []),
                ...$this->resolveScopeData($reviewable),
                ...$this->resolvePublishingData($shop, $modelData),
            ];

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

    private function resolveShop(Product|ProductCategory|Order|Shop $reviewable): ?Shop
    {
        if ($reviewable instanceof Shop) {
            return $reviewable;
        }

        return Shop::find($reviewable->shop_id);
    }

    private function resolveScopeData(Product|ProductCategory|Order|Shop $reviewable): array
    {
        if ($reviewable instanceof Order) {
            return [
                'scope'   => ReviewScopeEnum::ORDER->value,
                'shop_id' => $reviewable->shop_id,
            ];
        }

        if ($reviewable instanceof Product) {
            return [
                'scope'             => ReviewScopeEnum::PRODUCT->value,
                'master_product_id' => $reviewable->master_product_id,
                'product_id'        => $reviewable->id,
                'shop_id'           => $reviewable->shop_id,
            ];
        }

        if ($reviewable instanceof ProductCategory) {
            return [
                'scope'                      => ReviewScopeEnum::FAMILY->value,
                'master_product_category_id' => $reviewable->master_product_category_id,
                'product_category_id'        => $reviewable->id,
                'shop_id'                    => $reviewable->shop_id,
            ];
        }

        return [
            'scope'   => ReviewScopeEnum::SHOP->value,
            'shop_id' => $reviewable->id,
        ];
    }

    private function resolvePublishingData(?Shop $shop, array $modelData): array
    {
        $settings         = $shop?->settings ?? [];
        $approvalRequired = (bool) Arr::get($settings, 'reviews.data.approval_required', false);
        $isPublic         = (bool) data_get($modelData, 'is_public', true);

        if (!$isPublic) {
            return [
                'state'         => ReviewStateEnum::PRIVATE->value,
                'review_status' => $approvalRequired ? ReviewStatusEnum::PENDING->value : ReviewStatusEnum::APPROVED->value,
                'approved'      => !$approvalRequired,
                'auto_approved' => !$approvalRequired,
                'published_at'  => null,
                'show_after'    => null,
            ];
        }

        if ($approvalRequired) {
            return [
                'state'         => ReviewStateEnum::WAITING_APPROVAL->value,
                'review_status' => ReviewStatusEnum::PENDING->value,
                'approved'      => false,
                'auto_approved' => false,
                'published_at'  => null,
                'show_after'    => null,
            ];
        }

        $mode       = Arr::get($settings, 'reviews.auto_publishing.mode', ReviewAutoPublishingEnum::IMMEDIATELY->value);
        $delayHours = (int) Arr::get($settings, 'reviews.auto_publishing.delay_hours', 24);

        return match ($mode) {
            ReviewAutoPublishingEnum::DELAY->value => [
                'state'         => ReviewStateEnum::WAITING_APPROVAL->value,
                'review_status' => ReviewStatusEnum::APPROVED->value,
                'approved'      => true,
                'auto_approved' => true,
                'published_at'  => null,
                'show_after'    => now()->addHours($delayHours),
            ],
            ReviewAutoPublishingEnum::NEVER->value => [
                'state'         => ReviewStateEnum::WAITING_APPROVAL->value,
                'review_status' => ReviewStatusEnum::APPROVED->value,
                'approved'      => true,
                'auto_approved' => true,
                'published_at'  => null,
                'show_after'    => null,
            ],
            default => [
                'state'         => ReviewStateEnum::PUBLISHED->value,
                'review_status' => ReviewStatusEnum::APPROVED->value,
                'approved'      => true,
                'auto_approved' => true,
                'published_at'  => now(),
                'show_after'    => null,
            ],
        };
    }

    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'reviewable_type' => ['required', Rule::in(['order', 'product', 'family', 'shop'])],
            'reviewable_id'   => ['required', 'integer', 'min:1'],
            'rating'          => ['required', 'numeric', 'min:1', 'max:5'],
            'order_id'        => ['nullable', 'integer', 'exists:orders,id'],
        ]);
    }
}
