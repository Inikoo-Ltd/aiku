<?php

namespace App\Actions\Catalogue\Review;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Review;
use App\Models\Catalogue\ReviewableRatingStat;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function asController(Review $review, ActionRequest $request): JsonResponse|RedirectResponse
    {
        $isDeleted = $this->handle($review);

        if (!$request->expectsJson()) {
            return redirect()->back();
        }

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
            'number_reviews_like'    => $likeCount,
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
