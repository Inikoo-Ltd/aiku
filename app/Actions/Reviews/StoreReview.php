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
use App\Models\HumanResources\WorkSchedule;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Carbon\Carbon;
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

            $this->storeReviewWebImages($review);

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
        $approvalRequired = (bool) Arr::get($settings, 'reviews.data.approval_required', false);
        $ratingThreshold  = (int) Arr::get($settings, 'reviews.public_rating_threshold', 0);
        $rating           = $this->resolveRatingMain($modelData);
        $isPublic         = (bool) data_get($modelData, 'is_public', true);

        if ($ratingThreshold > 0 && $rating > $ratingThreshold) {
            $isPublic = true;
        }

        if (!$isPublic) {
            return [
                'state'         => ReviewStateEnum::PRIVATE,
                'review_status' => ReviewStatusEnum::NA,
                'approved'      => false,
                'auto_approved' => false,
                'is_public'     => false,
            ];
        }

        if ($approvalRequired) {
            return [
                'is_public'     => $isPublic,
                'state'         => ReviewStateEnum::WAITING_APPROVAL,
                'review_status' => ReviewStatusEnum::PENDING,
            ];
        }

        $mode       = Arr::get($settings, 'reviews.auto_publishing.mode', 'immediately');
        $delayHours = (int) Arr::get($settings, 'reviews.auto_publishing.delay_hours', 24);

        $publishingData = match ($mode) {
            'immediately' => $this->resolveImmediatelyPublished($shop),
            'never'       => [
                'state'         => ReviewStateEnum::REJECTED,
                'review_status' => ReviewStatusEnum::REJECTED,
                'is_public'     => false,
            ],
            default => [
                'state'           => ReviewStateEnum::WAITING_APPROVAL,
                'review_status'   => ReviewStatusEnum::PENDING,
                'auto_approve_at' => now()->addHours($delayHours),
            ],
        };

        return ['is_public' => $isPublic, ...$publishingData];
    }

    private function resolveImmediatelyPublished(?Shop $shop): array
    {
        if (!$shop) {
            return $this->publishedNow();
        }

        $effective = $shop->getEffectiveWorkSchedule();
        $schedule  = $effective['schedule'];
        $timezone  = $effective['timezone'];

        if ($schedule instanceof WorkSchedule) {
            $schedule->load('days');

            if (!$schedule->isOpenNow($timezone)) {
                return [
                    'state'           => ReviewStateEnum::WAITING_APPROVAL,
                    'review_status'   => ReviewStatusEnum::PENDING,
                    'auto_approve_at' => $this->resolveNextWorkingTime($schedule, $timezone),
                ];
            }
        }

        return $this->publishedNow();
    }

    private function publishedNow(): array
    {
        return [
            'state'         => ReviewStateEnum::PUBLISHED,
            'review_status' => ReviewStatusEnum::APPROVED,
            'approved'      => true,
            'auto_approved' => true,
            'published_at'  => now(),
        ];
    }

    private function resolveNextWorkingTime(WorkSchedule $schedule, string $timezone): Carbon
    {
        $now = Carbon::now($timezone);

        for ($i = 0; $i <= 7; $i++) {
            $date      = $now->copy()->addDays($i);
            $dayOfWeek = $date->dayOfWeekIso;

            $daySchedule = $schedule->days->firstWhere('day_of_week', $dayOfWeek);

            if (!$daySchedule || !$daySchedule->is_working_day || !$daySchedule->start_time) {
                continue;
            }

            $startTime = substr((string) $daySchedule->start_time, 0, 8);
            $start     = Carbon::createFromFormat('H:i:s', $startTime, $timezone)->setDateFrom($date);

            if ($i === 0 && $now->gte($start)) {
                continue;
            }

            return $start;
        }

        return $now;
    }

    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'rating'   => ['required', 'numeric', 'min:1', 'max:5'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);
    }
}
