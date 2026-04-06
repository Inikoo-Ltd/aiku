<?php

namespace App\Actions\Catalogue\Review;

use App\Enums\Catalogue\Review\ReviewMediaTypeEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Review;
use App\Models\Catalogue\ReviewableRatingStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateReview
{
    use AsAction;

    public function handle(Review $review, array $modelData): Review
    {
        return DB::transaction(function () use ($review, $modelData) {
            $mediaItems = Arr::pull($modelData, 'media', null);
            $images = Arr::pull($modelData, 'images', null);
            $videos = Arr::pull($modelData, 'videos', null);

            $review->update([
                'customer_id'          => data_get($modelData, 'customer_id', $review->customer_id),
                'status'               => data_get($modelData, 'status', $review->status?->value),
                'rating'               => data_get($modelData, 'rating', $review->rating),
                'title'                => data_get($modelData, 'title', $review->title),
                'message'              => data_get($modelData, 'message', $review->message),
                'is_verified_purchase' => data_get($modelData, 'is_verified_purchase', $review->is_verified_purchase),
                'meta'                 => data_get($modelData, 'meta', $review->meta ?? []),
            ]);

            if (is_array($mediaItems)) {
                $review->media()->delete();

                if (!empty($mediaItems)) {
                    $review->media()->createMany(
                        collect($mediaItems)
                            ->values()
                            ->map(function (array $item, int $index): array {
                                return [
                                    'media_id'   => data_get($item, 'media_id'),
                                    'type'       => data_get($item, 'type', ReviewMediaTypeEnum::IMAGE->value),
                                    'sort_order' => data_get($item, 'sort_order', $index),
                                    'meta'       => data_get($item, 'meta', []),
                                ];
                            })
                            ->all()
                    );
                }
            }

            if (is_array($images)) {
                $this->storeUploadedImages($review, $images);
            }

            if (is_array($videos)) {
                $this->storeUploadedVideos($review, $videos);
            }

            $reviewable = $review->reviewable;
            if ($reviewable instanceof Product || $reviewable instanceof ProductCategory) {
                $this->syncRatingStats($reviewable);
            }

            return $review->refresh()->load('media');
        });
    }

    public function asController(Review $review, ActionRequest $request): JsonResponse
    {
        $updatedReview = $this->handle($review, $request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => $updatedReview,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id'           => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'status'                => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'rating'                => ['sometimes', 'integer', 'min:1', 'max:5'],
            'title'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'message'               => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_verified_purchase'  => ['sometimes', 'boolean'],
            'meta'                  => ['sometimes', 'array'],
            'media'                 => ['sometimes', 'array'],
            'media.*.media_id'      => ['required', 'integer', 'exists:media,id'],
            'media.*.type'          => ['sometimes', Rule::enum(ReviewMediaTypeEnum::class)],
            'media.*.sort_order'    => ['sometimes', 'integer', 'min:0'],
            'media.*.meta'          => ['sometimes', 'array'],
            'images'                => ['sometimes', 'array'],
            'images.*'              => ['sometimes', File::image()->max(10 * 1024)],
            'videos'                => ['sometimes', 'array'],
            'videos.*'              => ['sometimes', File::types(['mp4', 'mov', 'avi', 'mkv', 'webm'])->max(50 * 1024)],
        ];
    }

    private function storeUploadedImages(Review $review, array $images): void
    {
        $nextSortOrder = (int) $review->media()->max('sort_order') + 1;

        foreach ($images as $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }

            $reviewMedia = $review->media()->create([
                'type'       => ReviewMediaTypeEnum::IMAGE->value,
                'sort_order' => $nextSortOrder,
                'meta'       => [],
            ]);

            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
                'checksum'     => md5_file($image->getPathName()),
            ];

            $storedMedia = StoreMediaFromFile::run($reviewMedia, $imageData, 'review_images', 'image');

            $reviewMedia->update([
                'media_id' => $storedMedia->id,
            ]);

            $nextSortOrder++;
        }
    }

    private function storeUploadedVideos(Review $review, array $videos): void
    {
        $nextSortOrder = (int) $review->media()->max('sort_order') + 1;

        foreach ($videos as $video) {
            if (!$video instanceof UploadedFile) {
                continue;
            }

            $reviewMedia = $review->media()->create([
                'type'       => ReviewMediaTypeEnum::VIDEO->value,
                'sort_order' => $nextSortOrder,
                'meta'       => [],
            ]);

            $videoData = [
                'path'         => $video->getPathName(),
                'originalName' => $video->getClientOriginalName(),
                'extension'    => $video->getClientOriginalExtension(),
                'checksum'     => md5_file($video->getPathName()),
            ];

            $storedMedia = StoreMediaFromFile::run($reviewMedia, $videoData, 'review_videos', 'file');

            $reviewMedia->update([
                'media_id' => $storedMedia->id,
            ]);

            $nextSortOrder++;
        }
    }

    private function syncRatingStats(Product|ProductCategory $reviewable): void
    {
        $baseQuery = Review::query()
            ->where('reviewable_type', $reviewable->getMorphClass())
            ->where('reviewable_id', $reviewable->id);

        $reviewsCount = (clone $baseQuery)->count();
        $verifiedReviewsCount = (clone $baseQuery)->where('is_verified_purchase', true)->count();
        $ratingAverage = round((float) ((clone $baseQuery)->avg('rating') ?? 0), 2);

        $ratingBreakdown = collect(range(1, 5))
            ->mapWithKeys(fn (int $rating): array => [(string) $rating => 0])
            ->merge(
                (clone $baseQuery)
                    ->selectRaw('rating, count(*) as aggregate')
                    ->groupBy('rating')
                    ->pluck('aggregate', 'rating')
                    ->map(fn ($count): int => (int) $count)
                    ->mapWithKeys(fn (int $count, $rating): array => [(string) $rating => $count])
            )
            ->all();

        $attributes = [
            'reviews_count'          => $reviewsCount,
            'verified_reviews_count' => $verifiedReviewsCount,
            'rating_average'         => $ratingAverage,
            'rating_breakdown'       => $ratingBreakdown,
            'last_reviewed_at'       => now(),
        ];

        $stat = ReviewableRatingStat::query()
            ->where('reviewable_type', $reviewable->getMorphClass())
            ->where('reviewable_id', $reviewable->id)
            ->first();

        if ($stat) {
            $stat->update($attributes);
        } else {
            ReviewableRatingStat::query()->create([
                'reviewable_type' => $reviewable->getMorphClass(),
                'reviewable_id'   => $reviewable->id,
                ...$attributes,
            ]);
        }
    }
}
