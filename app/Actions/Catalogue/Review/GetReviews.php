<?php

namespace App\Actions\Catalogue\Review;

use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ShopReview;
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
        $reviewableType = data_get($filters, 'reviewable_type', 'ProductCategory');
        $query = $this->resolveReviewQuery($reviewableType)
            ->with([
                'customer:id,name,contact_name,slug',
                'media:id,name,file_name,mime_type,size',
            ]);

        if ($reviewableId = data_get($filters, 'reviewable_id')) {
            $query->where($this->resolveReviewableColumn($reviewableType), (int) $reviewableId);
        }

        if ($reviewId = data_get($filters, 'review_id')) {
            $query->whereKey((int) $reviewId);
        }

        if ($customerId = data_get($filters, 'customer_id')) {
            $query->where('customer_id', (int) $customerId);
        }

        $status = data_get($filters, 'status');
        $query->where('status', $status ?: ReviewStatusEnum::Approved->value);

        $this->stats = $this->buildStats(clone $query, $filters);

        $sort = data_get($filters, 'sort', '-created_at');
        match ($sort) {
            'created_at' => $query->orderBy('created_at'),
            'rating' => $query->orderBy('rating_main'),
            '-rating' => $query->orderByDesc('rating_main'),
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
            'review_id'       => ['sometimes', 'integer', 'min:1'],
            'reviewable_type' => ['sometimes', Rule::in(['Product', 'ProductCategory', 'Shop', 'product_reviews', 'product_category_reviews', 'shop_reviews'])],
            'reviewable_id'   => ['sometimes', 'integer', 'min:1'],
            'customer_id'     => ['sometimes', 'integer', 'exists:customers,id'],
            'status'          => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'sort'            => ['sometimes', Rule::in(['created_at', '-created_at', 'rating', '-rating'])],
            'per_page'        => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'            => ['sometimes', 'integer', 'min:1'],
        ];
    }

    private function resolveReviewQuery(string $reviewableType): Builder
    {
        return match ($reviewableType) {
            'Product', 'product_reviews' => ProductReview::query(),
            'Shop', 'shop_reviews' => ShopReview::query(),
            default => ProductCategoryReview::query(),
        };
    }

    private function resolveReviewableColumn(string $reviewableType): string
    {
        return match ($reviewableType) {
            'Product', 'product_reviews' => 'product_id',
            'Shop', 'shop_reviews' => 'shop_id',
            default => 'product_category_id',
        };
    }

    private function buildStats(Builder $query, array $filters): array
    {
        $reviewableType = data_get($filters, 'reviewable_type', 'ProductCategory');
        $reviewableId = (int) data_get($filters, 'reviewable_id', 0);
        if ($reviewableType && $reviewableId > 0) {
            $reviewable = match ($reviewableType) {
                'Product', 'product_reviews' => Product::query()->with('reviewStats')->find($reviewableId),
                'Shop', 'shop_reviews' => Shop::query()->with('reviewStats')->find($reviewableId),
                default => ProductCategory::query()->with('reviewStats')->find($reviewableId),
            };

            $reviewableStat = $reviewable?->reviewStats;

            if ($reviewableStat) {
                return [
                    'total' => (int) ($reviewableStat->number_reviews ?? 0),
                    'average_rating' => (float) ($reviewableStat->average_rating_main ?? 0),
                    'like_count' => (int) ((clone $query)->sum('like_count')),
                    'status_approved' => (int) ($reviewableStat->number_reviews_approved ?? 0),
                    'status_pending' => (int) ($reviewableStat->number_reviews_pending ?? 0),
                    'status_rejected' => (int) ($reviewableStat->number_reviews_rejected ?? 0),
                    'number_reviews_rating_1' => (int) ($reviewableStat->number_rating_1 ?? 0),
                    'number_reviews_rating_2' => (int) ($reviewableStat->number_rating_2 ?? 0),
                    'number_reviews_rating_3' => (int) ($reviewableStat->number_rating_3 ?? 0),
                    'number_reviews_rating_4' => (int) ($reviewableStat->number_rating_4 ?? 0),
                    'number_reviews_rating_5' => (int) ($reviewableStat->number_rating_5 ?? 0),
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
            'average_rating' => round((float) ((clone $query)->avg('rating_main') ?? 0), 1),
            'like_count' => (int) ((clone $query)->sum('like_count')),
            'status_approved' => (int) ($statusCounts[ReviewStatusEnum::Approved->value] ?? 0),
            'status_pending' => (int) ($statusCounts[ReviewStatusEnum::Pending->value] ?? 0),
            'status_rejected' => (int) ($statusCounts[ReviewStatusEnum::Rejected->value] ?? 0),
            'number_reviews_rating_1' => (int) ((clone $query)->where('rating_main', 1)->count()),
            'number_reviews_rating_2' => (int) ((clone $query)->where('rating_main', 2)->count()),
            'number_reviews_rating_3' => (int) ((clone $query)->where('rating_main', 3)->count()),
            'number_reviews_rating_4' => (int) ((clone $query)->where('rating_main', 4)->count()),
            'number_reviews_rating_5' => (int) ((clone $query)->where('rating_main', 5)->count()),
        ];
    }
}
