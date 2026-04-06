<?php

namespace App\Actions\Catalogue\Review;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Review;
use App\Models\Catalogue\ReviewableRatingStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteReview
{
    use AsAction;

    public function handle(Review $review): bool
    {
        return DB::transaction(function () use ($review): bool {
            $review->loadMissing('reviewable');
            $reviewable = $review->reviewable;

            $review->media()->delete();
            $isDeleted = $review->delete();

            if ($isDeleted && ($reviewable instanceof Product || $reviewable instanceof ProductCategory)) {
                $this->syncRatingStats($reviewable);
            }

            return (bool) $isDeleted;
        });
    }

    public function asController(Review $review, ActionRequest $request): JsonResponse
    {
        $isDeleted = $this->handle($review);

        return response()->json([
            'status' => $isDeleted ? 'success' : 'failed',
            'message' => $isDeleted ? __('Review deleted successfully.') : __('Failed to delete review.'),
        ], $isDeleted ? 200 : 422);
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
