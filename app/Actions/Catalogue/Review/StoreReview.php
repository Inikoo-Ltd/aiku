<?php

namespace App\Actions\Catalogue\Review;

use App\Enums\Catalogue\Review\ReviewMediaTypeEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Review;
use App\Models\Catalogue\ReviewableRatingStat;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreReview
{
    use AsAction;

    public function handle(Product|ProductCategory $reviewable, array $modelData): Review
    {
        return DB::transaction(function () use ($reviewable, $modelData) {
            $mediaItems = data_get($modelData, 'media', []);
            $images = data_get($modelData, 'images', []);
            $videos = data_get($modelData, 'videos', []);

            $review = $reviewable->reviews()->create([
                'group_id'             => $reviewable->group_id,
                'organisation_id'      => $reviewable->organisation_id,
                'shop_id'              => $reviewable->shop_id,
                'customer_id'          => data_get($modelData, 'customer_id'),
                'status'               => data_get($modelData, 'status', ReviewStatusEnum::Approved->value),
                'rating'               => data_get($modelData, 'rating'),
                'message'              => data_get($modelData, 'message'),
                'order_id'             => data_get($modelData, 'order_id'),
                'like_count'           => 0,
                'meta'                 => data_get($modelData, 'meta', []),
            ]);

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

            if (!empty($images)) {
                $this->storeUploadedImages($review, $reviewable, $images);
            }

            if (!empty($videos)) {
                $this->storeUploadedVideos($review, $reviewable, $videos);
            }

            $this->syncRatingStats($reviewable);

            return $review->refresh();
        });
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $reviewable = $this->resolveReviewable(
            $request->validated('reviewable_type'),
            (int) $request->validated('reviewable_id')
        );

        $review = $this->handle($reviewable, $request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => $review->load('media'),
        ]);
    }

    public function rules(): array
    {
        return [
            'reviewable_type'     => ['required', Rule::in(['Product', 'ProductCategory'])],
            'reviewable_id'       => ['required', 'integer', 'min:1'],
            'customer_id'         => ['nullable', 'integer', 'exists:customers,id'],
            'status'              => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'rating'              => ['required', 'integer', 'min:1', 'max:5'],
            'message'             => ['nullable', 'string', 'max:5000'],
            'order_id'            => ['nullable', 'integer', 'exists:orders,id'],
            'meta'                => ['sometimes', 'array'],
            'media'               => ['sometimes', 'array'],
            'media.*.media_id'    => ['required', 'integer', 'exists:media,id'],
            'media.*.type'        => ['sometimes', Rule::enum(ReviewMediaTypeEnum::class)],
            'media.*.sort_order'  => ['sometimes', 'integer', 'min:0'],
            'media.*.meta'        => ['sometimes', 'array'],
            'images'              => ['sometimes', 'array'],
            'images.*'            => ['sometimes', File::image()->max(10 * 1024)],
            'videos'              => ['sometimes', 'array'],
            'videos.*'            => ['sometimes', File::types(['mp4', 'webm'])->max(10 * 1024)],
        ];
    }

    private function storeUploadedImages(Review $review, Product|ProductCategory $reviewable, array $images): void
    {
        $nextSortOrder = (int) $review->media()->max('sort_order') + 1;

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

            $storedMedia = StoreMediaFromFile::run($reviewable, $imageData, 'review_images', 'image');

            $review->media()->create([
                'media_id'   => $storedMedia->id,
                'type'       => ReviewMediaTypeEnum::IMAGE->value,
                'sort_order' => $nextSortOrder,
                'meta'       => [],
            ]);

            $nextSortOrder++;
        }
    }

    private function storeUploadedVideos(Review $review, Product|ProductCategory $reviewable, array $videos): void
    {
        $nextSortOrder = (int) $review->media()->max('sort_order') + 1;

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

            $storedMedia = StoreMediaFromFile::run($reviewable, $videoData, 'review_videos', 'file');

            $review->media()->create([
                'media_id'   => $storedMedia->id,
                'type'       => ReviewMediaTypeEnum::VIDEO->value,
                'sort_order' => $nextSortOrder,
                'meta'       => [],
            ]);

            $nextSortOrder++;
        }
    }

    private function resolveReviewable(string $reviewableType, int $reviewableId): Product|ProductCategory
    {
        return match ($reviewableType) {
            'Product' => Product::query()->findOrFail($reviewableId),
            'ProductCategory' => ProductCategory::query()->findOrFail($reviewableId),
        };
    }

    private function syncRatingStats(Product|ProductCategory $reviewable): void
    {
        $baseQuery = Review::query()
            ->where('reviewable_type', $reviewable->getMorphClass())
            ->where('reviewable_id', $reviewable->id);

        $reviewsCount = (clone $baseQuery)->count();
        $likeCount = (clone $baseQuery)->sum('like_count');
        $ratingAverage = round((float) ((clone $baseQuery)->avg('rating') ?? 0), 2);

        $ratingBreakdown = [
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
        ];

        $ratingCounts = (clone $baseQuery)
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        foreach ($ratingCounts as $rating => $count) {
            $ratingKey = (string) ((int) $rating);

            if (array_key_exists($ratingKey, $ratingBreakdown)) {
                $ratingBreakdown[$ratingKey] = (int) $count;
            }
        }

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        $attributes = [
            'reviews_count'          => $reviewsCount,
            'number_reviews_like'     => $likeCount,
            'rating_average'         => $ratingAverage,
            'rating_breakdown'       => $ratingBreakdown,
            'number_reviews_state_pending' => (int) ($statusCounts[ReviewStatusEnum::Pending->value] ?? 0),
            'number_reviews_state_approved' => (int) ($statusCounts[ReviewStatusEnum::Approved->value] ?? 0),
            'number_reviews_state_rejected' => (int) ($statusCounts[ReviewStatusEnum::Rejected->value] ?? 0),
            'number_reviews_rating_1' => (int) ($ratingBreakdown['1'] ?? 0),
            'number_reviews_rating_2' => (int) ($ratingBreakdown['2'] ?? 0),
            'number_reviews_rating_3' => (int) ($ratingBreakdown['3'] ?? 0),
            'number_reviews_rating_4' => (int) ($ratingBreakdown['4'] ?? 0),
            'number_reviews_rating_5' => (int) ($ratingBreakdown['5'] ?? 0),
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
