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
                'title'                => data_get($modelData, 'title'),
                'message'              => data_get($modelData, 'message'),
                'is_verified_purchase' => data_get($modelData, 'is_verified_purchase', false),
                'helpful_count'        => 0,
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
                $this->storeUploadedImages($review, $images);
            }

            if (!empty($videos)) {
                $this->storeUploadedVideos($review, $videos);
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
            'title'               => ['nullable', 'string', 'max:255'],
            'message'             => ['nullable', 'string', 'max:5000'],
            'is_verified_purchase' => ['sometimes', 'boolean'],
            'meta'                => ['sometimes', 'array'],
            'media'               => ['sometimes', 'array'],
            'media.*.media_id'    => ['required', 'integer', 'exists:media,id'],
            'media.*.type'        => ['sometimes', Rule::enum(ReviewMediaTypeEnum::class)],
            'media.*.sort_order'  => ['sometimes', 'integer', 'min:0'],
            'media.*.meta'        => ['sometimes', 'array'],
            'images'              => ['sometimes', 'array'],
            'images.*'            => ['sometimes', File::image()->max(10 * 1024)],
            'videos'              => ['sometimes', 'array'],
            'videos.*'            => ['sometimes', File::types(['mp4', 'webm'])->max(5 * 1024)],
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
