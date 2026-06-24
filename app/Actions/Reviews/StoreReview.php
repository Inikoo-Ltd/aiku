<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 12:03:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreReview
{
    use AsAction;
    use HasReviewHydrators;

    public function prepareForValidation(ActionRequest $request): void
    {
        $nullableKeys = ['rating_a', 'rating_b', 'rating_c', 'rating_d', 'rating_e', 'customer_id'];

        $updates = [];
        foreach ($nullableKeys as $key) {
            if (!$request->has($key)) {
                continue;
            }

            $value = $request->input($key);
            if ($value === '' || $value === 'null') {
                $updates[$key] = null;
            }
        }

        if ($updates !== []) {
            $request->merge($updates);
        }
    }

    /**
     * @throws \Throwable
     */
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
                'status'          => data_get($modelData, 'status', ReviewStatusEnum::APPROVED->value),
                'title'           => data_get($modelData, 'title'),
                'message'         => data_get($modelData, 'message'),
                'likes'      => data_get($modelData, 'likes', 0),
                'meta'            => data_get($modelData, 'meta', []),
            ];

            if ($reviewable instanceof Order) {
                $reviewData['shop_id'] = $reviewable->shop_id;
            } elseif ($reviewable instanceof Product) {
                $reviewData['master_product_id'] = $reviewable->master_product_id;
                $reviewData['product_id']        = $reviewable->id;
                $reviewData['shop_id']           = $reviewable->shop_id;
            } elseif ($reviewable instanceof ProductCategory) {
                $reviewData['master_product_category_id'] = $reviewable->master_product_category_id;
                $reviewData['product_category_id']        = $reviewable->id;
                $reviewData['shop_id']                    = $reviewable->shop_id;
            } elseif ($reviewable instanceof Shop) {
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
        return [
            'reviewable_type' => ['required', Rule::in(['Product', 'ProductCategory', 'Shop', 'product_reviews', 'product_category_reviews', 'shop_reviews'])],
            'reviewable_id'   => ['required', 'integer', 'min:1'],
            'customer_id'     => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'status'          => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'rating'          => ['required', 'numeric', 'min:1', 'max:5'],
            'rating_a'        => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_b'        => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_c'        => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_d'        => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_e'        => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'title'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'message'         => ['sometimes', 'nullable', 'string', 'max:5000'],
            'show_after'      => ['sometimes', 'nullable', 'date'],
            'is_public'       => ['sometimes', 'boolean'],
            'order_id'        => ['nullable', 'integer', 'exists:orders,id'],
            'likes'      => ['sometimes', 'integer', 'min:0'],
            'meta'            => ['sometimes', 'array'],
            'images'          => ['sometimes', 'array'],
            'images.*'        => ['sometimes', File::image()->max(50 * 1024)],
            'videos'          => ['sometimes', 'array'],
            'videos.*'        => ['sometimes', File::types(['mp4', 'webm'])->max(50 * 1024)],
        ];
    }

    private function storeUploadedImages(Model $review, array $images): void
    {
        foreach ($images as $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }

            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
                'checksum'     => md5_file($image->getPathName()),
            ];

            StoreMediaFromFile::run($review, $imageData, 'review_images', 'image');
        }
    }

    private function storeUploadedVideos(Model $review, array $videos): void
    {
        foreach ($videos as $video) {
            if (!$video instanceof UploadedFile) {
                continue;
            }

            $videoData = [
                'path'         => $video->getPathName(),
                'originalName' => $video->getClientOriginalName(),
                'extension'    => $video->getClientOriginalExtension(),
                'checksum'     => md5_file($video->getPathName()),
            ];

            StoreMediaFromFile::run($review, $videoData, 'review_videos', 'file');
        }
    }

    private function resolveRatingMain(array $modelData): float
    {
        $detailedRatings = collect([
            data_get($modelData, 'rating_a'),
            data_get($modelData, 'rating_b'),
            data_get($modelData, 'rating_c'),
            data_get($modelData, 'rating_d'),
            data_get($modelData, 'rating_e'),
        ])->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float)$value)->values();

        if ($detailedRatings->isNotEmpty()) {
            return round((float)$detailedRatings->avg(), 2);
        }

        $rating = data_get($modelData, 'rating');
        if (is_numeric($rating)) {
            return round((float)$rating, 2);
        }

        return 5;
    }
}
