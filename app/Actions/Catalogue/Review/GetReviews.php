<?php

namespace App\Actions\Catalogue\Review;

use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Review;
use App\Models\Catalogue\ReviewableRatingStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetReviews
{
    use AsAction;

    private array $stats = [];

    public function handle(array $filters): LengthAwarePaginator
    {
        $query = Review::query()
            ->with([
                'customer:id,name,slug',
                'media.media:id,name,file_name,mime_type,size',
            ]);

        $reviewableType = data_get($filters, 'reviewable_type');
        if ($reviewableType) {
            $query->where('reviewable_type', $this->resolveReviewableMorphClass($reviewableType));
        }

        if ($reviewableId = data_get($filters, 'reviewable_id')) {
            $query->where('reviewable_id', (int) $reviewableId);
        }

        if ($reviewId = data_get($filters, 'review_id')) {
            $query->whereKey((int) $reviewId);
        }

        if ($customerId = data_get($filters, 'customer_id')) {
            $query->where('customer_id', (int) $customerId);
        }

        if ($status = data_get($filters, 'status')) {
            $query->where('status', $status);
        }

        $this->stats = $this->buildStats(clone $query, $filters);

        $sort = data_get($filters, 'sort', '-created_at');
        match ($sort) {
            'created_at' => $query->orderBy('created_at'),
            'rating' => $query->orderBy('rating'),
            '-rating' => $query->orderByDesc('rating'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate((int) data_get($filters, 'per_page', 15))->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        return $this->handle($request->validated());
    }

    public function jsonResponse(LengthAwarePaginator $reviews): AnonymousResourceCollection
    {
        return ReviewsResource::collection($reviews)->additional([
            'stats' => $this->stats,
        ]);
    }

    public function rules(): array
    {
        return [
            'review_id'       => ['sometimes', 'integer', 'exists:reviews,id'],
            'reviewable_type' => ['sometimes', Rule::in(['Product', 'ProductCategory'])],
            'reviewable_id'   => ['sometimes', 'integer', 'min:1'],
            'customer_id'     => ['sometimes', 'integer', 'exists:customers,id'],
            'status'          => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'sort'            => ['sometimes', Rule::in(['created_at', '-created_at', 'rating', '-rating'])],
            'per_page'        => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'            => ['sometimes', 'integer', 'min:1'],
        ];
    }

    private function resolveReviewableMorphClass(string $reviewableType): string
    {
        return match ($reviewableType) {
            'Product' => (new Product())->getMorphClass(),
            'ProductCategory' => (new ProductCategory())->getMorphClass(),
        };
    }

    private function buildStats(Builder $query, array $filters): array
    {
        $reviewableType = data_get($filters, 'reviewable_type');
        $reviewableId = (int) data_get($filters, 'reviewable_id', 0);
        if ($reviewableType && $reviewableId > 0) {
            $reviewableStat = ReviewableRatingStat::query()
                ->where('reviewable_type', $this->resolveReviewableMorphClass($reviewableType))
                ->where('reviewable_id', $reviewableId)
                ->first();

            if ($reviewableStat) {
                $ratingBreakdownFromColumns = [
                    '1' => (int) ($reviewableStat->number_reviews_rating_1 ?? 0),
                    '2' => (int) ($reviewableStat->number_reviews_rating_2 ?? 0),
                    '3' => (int) ($reviewableStat->number_reviews_rating_3 ?? 0),
                    '4' => (int) ($reviewableStat->number_reviews_rating_4 ?? 0),
                    '5' => (int) ($reviewableStat->number_reviews_rating_5 ?? 0),
                ];

                $ratingBreakdown = $reviewableStat->rating_breakdown ?? [];
                if (empty($ratingBreakdown)) {
                    $ratingBreakdown = $ratingBreakdownFromColumns;
                }

                return [
                    'total' => (int) $reviewableStat->reviews_count,
                    'average_rating' => (float) $reviewableStat->rating_average,
                    'like_count' => (int) $reviewableStat->number_reviews_like,
                    'status_approved' => (int) ($reviewableStat->number_reviews_state_approved ?? 0),
                    'status_pending' => (int) ($reviewableStat->number_reviews_state_pending ?? 0),
                    'status_rejected' => (int) ($reviewableStat->number_reviews_state_rejected ?? 0),
                    'number_reviews_rating_1' => $ratingBreakdownFromColumns['1'],
                    'number_reviews_rating_2' => $ratingBreakdownFromColumns['2'],
                    'number_reviews_rating_3' => $ratingBreakdownFromColumns['3'],
                    'number_reviews_rating_4' => $ratingBreakdownFromColumns['4'],
                    'number_reviews_rating_5' => $ratingBreakdownFromColumns['5'],
                ];
            }
        }

        $total = (clone $query)->count();

        $statusCounts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $total,
            'average_rating' => round((float) ((clone $query)->avg('rating') ?? 0), 1),
            'like_count' => (int) ((clone $query)->sum('like_count')),
            'status_approved' => (int) ($statusCounts[ReviewStatusEnum::Approved->value] ?? 0),
            'status_pending' => (int) ($statusCounts[ReviewStatusEnum::Pending->value] ?? 0),
            'status_rejected' => (int) ($statusCounts[ReviewStatusEnum::Rejected->value] ?? 0),
            'number_reviews_rating_1' => (int) ((clone $query)->where('rating', 1)->count()),
            'number_reviews_rating_2' => (int) ((clone $query)->where('rating', 2)->count()),
            'number_reviews_rating_3' => (int) ((clone $query)->where('rating', 3)->count()),
            'number_reviews_rating_4' => (int) ((clone $query)->where('rating', 4)->count()),
            'number_reviews_rating_5' => (int) ((clone $query)->where('rating', 5)->count()),
        ];
    }
}
