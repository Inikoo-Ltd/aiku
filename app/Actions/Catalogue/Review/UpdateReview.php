<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 20:00:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Review;

use App\Actions\Catalogue\Review\Traits\HasReviewCommonLogic;
use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Models\Reviews\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateReview extends OrgAction
{
    use HasReviewCommonLogic;
    use HasReviewHydrators;

    public function rules(): array
    {
        return $this->commonRules();
    }

    /**
     * @throws \Throwable
     */
    public function handle(Review $review, array $modelData): Review
    {
        $updatedReview = DB::transaction(function () use ($review, $modelData) {
            $images = Arr::pull($modelData, 'images');
            $videos = Arr::pull($modelData, 'videos');

            $newState = data_get($modelData, 'state', $review->state?->value);

            $review->update([
                'customer_id'   => data_get($modelData, 'customer_id', $review->customer_id),
                'state'         => $newState,
                'review_status' => data_get($modelData, 'review_status', $review->review_status?->value),
                'approved'      => data_get($modelData, 'approved', $review->approved),
                'auto_approved' => data_get($modelData, 'auto_approved', $review->auto_approved),
                'published_at'  => $this->resolvePublishedAt($newState, $review, $modelData),
                'removed_at'    => $this->resolveRemovedAt($newState, $review, $modelData),
                'rating_main'   => $this->resolveRatingMain($modelData, $review),
                'rating_a'      => data_get($modelData, 'rating_a', $review->rating_a),
                'rating_b'      => data_get($modelData, 'rating_b', $review->rating_b),
                'rating_c'      => data_get($modelData, 'rating_c', $review->rating_c),
                'rating_d'      => data_get($modelData, 'rating_d', $review->rating_d),
                'rating_e'      => data_get($modelData, 'rating_e', $review->rating_e),
                'title'         => data_get($modelData, 'title', $review->title),
                'message'       => data_get($modelData, 'message', $review->message),
                'auto_approve_at'    => data_get($modelData, 'auto_approve_at', $review->auto_approve_at),
                'is_public'     => data_get($modelData, 'is_public', $review->is_public),
                'order_id'      => data_get($modelData, 'order_id', $review->order_id),
                'likes'         => data_get($modelData, 'likes', $review->likes),
                'meta'          => data_get($modelData, 'meta', $review->meta ?? []),
            ]);

            if (is_array($images)) {
                $this->storeUploadedImages($review, $images);
            }

            if (is_array($videos)) {
                $this->storeUploadedVideos($review, $videos);
            }

            $this->storeReviewWebImages($review);

            return $review->refresh()->load('media');
        });

        $this->reviewHydrators($updatedReview);

        return $updatedReview;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Review $review, ActionRequest $request): Review
    {
        $this->initialisationFromShop($review->shop, $request);

        return $this->handle($review, $this->validatedData);
    }

    public function jsonResponse(Review $review): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $review->load('media'),
        ]);
    }

    private function resolvePublishedAt(string $newState, Review $review, array $modelData): mixed
    {
        if (\array_key_exists('published_at', $modelData)) {
            return $modelData['published_at'];
        }

        if ($newState === ReviewStateEnum::PUBLISHED->value && $review->published_at === null) {
            return now();
        }

        return $review->published_at;
    }

    private function resolveRemovedAt(string $newState, Review $review, array $modelData): mixed
    {
        if (\array_key_exists('removed_at', $modelData)) {
            return $modelData['removed_at'];
        }

        if ($newState === ReviewStateEnum::REMOVED->value && $review->removed_at === null) {
            return now();
        }

        return $review->removed_at;
    }

    public function htmlResponse(Review $review, ActionRequest $request): RedirectResponse
    {
        $request->route()?->getName();

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Review updated successfully.'),
        ]);
    }

}
