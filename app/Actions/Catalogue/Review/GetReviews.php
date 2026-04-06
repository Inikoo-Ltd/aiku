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
                return [
                    'total' => (int) $reviewableStat->reviews_count,
                    'average_rating' => (float) $reviewableStat->rating_average,
                    'verified' => (int) $reviewableStat->verified_reviews_count,
                    'helpful_count' => (int) ((clone $query)->sum('helpful_count') ?? 0),
                    'status_approved' => (int) ((clone $query)->where('status', ReviewStatusEnum::Approved->value)->count()),
                    'status_pending' => (int) ((clone $query)->where('status', ReviewStatusEnum::Pending->value)->count()),
                    'status_rejected' => (int) ((clone $query)->where('status', ReviewStatusEnum::Rejected->value)->count()),
                    'rating_breakdown' => $reviewableStat->rating_breakdown ?? [],
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
            'verified' => (int) ((clone $query)->where('is_verified_purchase', true)->count()),
            'helpful_count' => (int) ((clone $query)->sum('helpful_count') ?? 0),
            'status_approved' => (int) ($statusCounts[ReviewStatusEnum::Approved->value] ?? 0),
            'status_pending' => (int) ($statusCounts[ReviewStatusEnum::Pending->value] ?? 0),
            'status_rejected' => (int) ($statusCounts[ReviewStatusEnum::Rejected->value] ?? 0),
            'rating_breakdown' => [],
        ];
    }
}
