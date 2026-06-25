<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 20:00:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Review\Traits;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Reviews\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

trait HasReviewCommonLogic
{
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

    protected function commonRules(): array
    {
        return [
            'customer_id'   => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'review_status' => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'state'         => ['sometimes', Rule::enum(ReviewStateEnum::class)],
            'rating'        => ['sometimes', 'numeric', 'min:1', 'max:5'],
            'rating_a'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_b'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_c'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_d'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_e'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'title'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'message'       => ['sometimes', 'nullable', 'string', 'max:5000'],
            'show_after'    => ['sometimes', 'nullable', 'date'],
            'is_public'     => ['sometimes', 'boolean'],
            'order_id'      => ['sometimes', 'nullable', 'integer', 'exists:orders,id'],
            'likes'         => ['sometimes', 'integer', 'min:0'],
            'meta'          => ['sometimes', 'array'],
            'images'        => ['sometimes', 'array'],
            'images.*'      => ['sometimes', File::image()->max(50 * 1024)],
            'videos'        => ['sometimes', 'array'],
            'videos.*'      => ['sometimes', File::types(['mp4', 'webm'])->max(50 * 1024)],
        ];
    }

    protected function storeUploadedImages(Model $review, array $images): void
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

    protected function storeUploadedVideos(Model $review, array $videos): void
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

    protected function resolveRatingMain(array $modelData, ?Review $review = null): float
    {
        $dimensionKeys = ['rating_a', 'rating_b', 'rating_c', 'rating_d', 'rating_e'];
        $dimensionsWereProvided = collect($dimensionKeys)->contains(fn (string $key): bool => array_key_exists($key, $modelData));

        $rating = data_get($modelData, 'rating');
        $ratingWasProvided = array_key_exists('rating', $modelData) && is_numeric($rating);

        if (!$dimensionsWereProvided && !$ratingWasProvided) {
            return (float) ($review?->rating_main ?? ($review ? 0 : 5));
        }

        $detailedRatings = collect($dimensionKeys)
            ->map(fn (string $key) => data_get($modelData, $key))
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): float => (float) $value)
            ->values();

        if ($detailedRatings->isNotEmpty()) {
            return round((float) $detailedRatings->avg(), 2);
        }

        if ($ratingWasProvided) {
            return round((float) $rating, 2);
        }

        return (float) ($review?->rating_main ?? ($review ? 0 : 5));
    }
}
