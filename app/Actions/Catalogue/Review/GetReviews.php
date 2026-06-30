<?php

namespace App\Actions\Catalogue\Review;

use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetReviews
{
    use AsAction;

    private array $stats = [];
    private array $ratingLabels = [];

    public function handle(array $filters): LengthAwarePaginator
    {
        $query = Review::with([
            'customer:id,name,contact_name,slug',
            'media:id,name,file_name,mime_type,size',
        ]);

        if ($scope = data_get($filters, 'scope')) {
            $query->where('scope', $scope);
        }

        if ($reviewableId = data_get($filters, 'reviewable_id')) {
            $column = $this->resolveScopeColumn($scope ?? '');
            if ($column) {
                $query->where($column, (int) $reviewableId);
            }
        }

        if ($reviewId = data_get($filters, 'review_id')) {
            $query->whereKey((int) $reviewId);
        }

        if ($customerId = data_get($filters, 'customer_id')) {
            $query->where('customer_id', (int) $customerId);
        }

        if ($reviewStatus = data_get($filters, 'review_status')) {
            $query->where('review_status', $reviewStatus);
        }

        $state = data_get($filters, 'state', ReviewStateEnum::PUBLISHED->value);
        $query->where('state', $state);

        $this->stats        = $this->buildStats(clone $query, $filters);
        $this->ratingLabels = $this->buildRatingLabels($filters);

        match (data_get($filters, 'sort', '-created_at')) {
            'created_at' => $query->orderBy('created_at'),
            'rating'     => $query->orderBy('rating_main'),
            '-rating'    => $query->orderByDesc('rating_main'),
            default      => $query->orderByDesc('created_at'),
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
            'stats'         => $this->stats,
            'rating_labels' => $this->ratingLabels,
        ]);
    }

    public function rules(): array
    {
        return [
            'review_id'     => ['sometimes', 'integer', 'min:1'],
            'scope'         => ['sometimes', Rule::enum(ReviewScopeEnum::class)],
            'reviewable_id' => ['sometimes', 'integer', 'min:1'],
            'customer_id'   => ['sometimes', 'integer', 'exists:customers,id'],
            'review_status' => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
            'state'         => ['sometimes', Rule::enum(ReviewStateEnum::class)],
            'sort'          => ['sometimes', Rule::in(['created_at', '-created_at', 'rating', '-rating'])],
            'per_page'      => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'          => ['sometimes', 'integer', 'min:1'],
        ];
    }

    private function resolveScopeColumn(string $scope): ?string
    {
        return match ($scope) {
            ReviewScopeEnum::PRODUCT->value => 'product_id',
            ReviewScopeEnum::FAMILY->value  => 'product_category_id',
            ReviewScopeEnum::SHOP->value    => 'shop_id',
            ReviewScopeEnum::ORDER->value   => 'order_id',
            default                         => null,
        };
    }

    private function buildStats(Builder $query, array $filters): array
    {
        $scope        = data_get($filters, 'scope', '');
        $reviewableId = (int) data_get($filters, 'reviewable_id', 0);

        if ($scope && $reviewableId > 0) {
            $reviewable = match ($scope) {
                ReviewScopeEnum::PRODUCT->value => Product::query()->with('reviewStats')->find($reviewableId),
                ReviewScopeEnum::SHOP->value    => Shop::query()->with('reviewStats')->find($reviewableId),
                ReviewScopeEnum::FAMILY->value  => ProductCategory::query()->with('reviewStats')->find($reviewableId),
                ReviewScopeEnum::ORDER->value   => Order::query()->with('reviewStats')->find($reviewableId),
                default                         => null,
            };

            $reviewableStat = $reviewable?->reviewStats;

            if ($reviewableStat) {
                return [
                    'total'                   => (int) ($reviewableStat->number_reviews ?? 0),
                    'average_rating'          => (float) ($reviewableStat->average_rating_main ?? 0),
                    'likes'                   => (int) ((clone $query)->sum('likes')),
                    'status_approved'         => (int) ($reviewableStat->number_reviews_approved ?? 0),
                    'status_pending'          => (int) ($reviewableStat->number_reviews_pending ?? 0),
                    'status_rejected'         => (int) ($reviewableStat->number_reviews_rejected ?? 0),
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
            ->selectRaw('review_status, count(*) as aggregate')
            ->groupBy('review_status')
            ->pluck('aggregate', 'review_status');

        return [
            'total'                   => $total,
            'average_rating'          => round((float) ((clone $query)->avg('rating_main') ?? 0), 1),
            'likes'                   => (int) ((clone $query)->sum('likes')),
            'status_approved'         => (int) ($statusCounts[ReviewStatusEnum::APPROVED->value] ?? 0),
            'status_pending'          => (int) ($statusCounts[ReviewStatusEnum::PENDING->value] ?? 0),
            'status_rejected'         => (int) ($statusCounts[ReviewStatusEnum::REJECTED->value] ?? 0),
            'number_reviews_rating_1' => (clone $query)->where('rating_main', 1)->count(),
            'number_reviews_rating_2' => (clone $query)->where('rating_main', 2)->count(),
            'number_reviews_rating_3' => (clone $query)->where('rating_main', 3)->count(),
            'number_reviews_rating_4' => (clone $query)->where('rating_main', 4)->count(),
            'number_reviews_rating_5' => (clone $query)->where('rating_main', 5)->count(),
        ];
    }

    private function buildRatingLabels(array $filters): array
    {
        $scope        = (string) data_get($filters, 'scope', '');
        $reviewableId = (int) data_get($filters, 'reviewable_id', 0);

        if ($scope === '' || $reviewableId < 1) {
            return [];
        }

        $reviewable = match ($scope) {
            ReviewScopeEnum::PRODUCT->value => Product::query()->find($reviewableId),
            ReviewScopeEnum::SHOP->value    => Shop::query()->find($reviewableId),
            ReviewScopeEnum::FAMILY->value  => ProductCategory::query()->find($reviewableId),
            ReviewScopeEnum::ORDER->value   => Order::query()->find($reviewableId),
            default                         => null,
        };

        if (!$reviewable) {
            return [];
        }

        return ReviewsResource::ratingLabelsFor($reviewable);
    }
}
