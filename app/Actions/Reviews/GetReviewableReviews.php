<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 18:35:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class GetReviewableReviews extends IrisAction
{
    private array $stats = [];
    private array $customers = [];
    private array $ratingLabels = [];

    public function handle(Product|ProductCategory|Order|Shop $reviewable, array $filters = []): LengthAwarePaginator
    {
        $query = Review::where($this->reviewableColumn($reviewable), $reviewable->id)
            ->where('scope', $this->reviewableScope($reviewable))
            ->where('state', ReviewStateEnum::PUBLISHED->value)
            ->with([
                'customer:id,name,contact_name,slug',
                'media:id,name,file_name,mime_type,size',
                'replies',
            ]);

        $this->ratingLabels = ReviewsResource::ratingLabelsFor($reviewable);
        $this->stats        = $this->buildStats($reviewable, clone $query);
        $this->customers    = $this->buildCustomers($reviewable, clone $query);

        match (data_get($filters, 'sort', '-created_at')) {
            'created_at' => $query->orderBy('created_at'),
            'rating'     => $query->orderBy('rating_main'),
            '-rating'    => $query->orderByDesc('rating_main'),
            default      => $query->orderByDesc('created_at'),
        };

        return $query->paginate((int) data_get($filters, 'per_page', 15))->withQueryString();
    }

    public function inShop(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        return $this->handle($shop, $request->validated());
    }

    public function inProduct(Product $product, ActionRequest $request): LengthAwarePaginator
    {
        return $this->handle($product, $request->validated());
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        return $this->handle($productCategory, $request->validated());
    }

    public function jsonResponse(LengthAwarePaginator $reviews): AnonymousResourceCollection
    {
        return ReviewsResource::collection($reviews)->additional([
            'stats'         => $this->stats,
            'customers'     => [
                'data' => $this->customers,
                'meta' => [
                    'total' => count($this->customers),
                ],
            ],
            'rating_labels' => $this->ratingLabels,
        ]);
    }

    public function rules(): array
    {
        return [
            'sort'     => ['sometimes', Rule::in(['created_at', '-created_at', 'rating', '-rating'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'     => ['sometimes', 'integer', 'min:1'],
        ];
    }

    private function reviewableColumn(Product|ProductCategory|Order|Shop $reviewable): string
    {
        return match (true) {
            $reviewable instanceof Product         => 'product_id',
            $reviewable instanceof Shop            => 'shop_id',
            $reviewable instanceof Order           => 'order_id',
            $reviewable instanceof ProductCategory => 'product_category_id',
        };
    }

    private function reviewableScope(Product|ProductCategory|Order|Shop $reviewable): string
    {
        return match (true) {
            $reviewable instanceof Product         => ReviewScopeEnum::PRODUCT->value,
            $reviewable instanceof Shop            => ReviewScopeEnum::SHOP->value,
            $reviewable instanceof Order           => ReviewScopeEnum::ORDER->value,
            $reviewable instanceof ProductCategory => ReviewScopeEnum::FAMILY->value,
        };
    }

    private function buildCustomers(Product|ProductCategory|Order|Shop $reviewable, Builder $reviewQuery): array
    {
        $reviewTable      = $reviewQuery->getModel()->getTable();
        $reviewableColumn = $this->reviewableColumn($reviewable);

        return Customer::query()
            ->join($reviewTable, "$reviewTable.customer_id", '=', 'customers.id')
            ->where("$reviewTable.$reviewableColumn", $reviewable->id)
            ->where("$reviewTable.state", ReviewStateEnum::PUBLISHED->value)
            ->whereNull("$reviewTable.deleted_at")
            ->selectRaw(
                "
                customers.id as customer_id,
                COALESCE(NULLIF(customers.contact_name, ''), customers.name) as label,
                customers.contact_name,
                customers.slug
            "
            )
            ->distinct()
            ->orderBy('label')
            ->get()
            ->map(fn ($row): array => [
                'customer_id'  => (int) data_get($row, 'customer_id'),
                'label'        => (string) data_get($row, 'label'),
                'contact_name' => data_get($row, 'contact_name'),
                'slug'         => data_get($row, 'slug'),
            ])
            ->values()
            ->all();
    }

    private function buildStats(Product|ProductCategory|Order|Shop $reviewable, Builder $reviewQuery): array
    {
        $reviewable->loadMissing('reviewStats');

        $reviewStat = $reviewable->reviewStats;
        if ($reviewStat) {
            $averageByDimension = [
                'a' => round((float) ($reviewStat->average_rating_a ?? 0), 2),
                'b' => round((float) ($reviewStat->average_rating_b ?? 0), 2),
                'c' => round((float) ($reviewStat->average_rating_c ?? 0), 2),
                'd' => round((float) ($reviewStat->average_rating_d ?? 0), 2),
                'e' => round((float) ($reviewStat->average_rating_e ?? 0), 2),
            ];

            $categoryRatings = collect($this->ratingLabels)
                ->map(function (array $label) use ($averageByDimension): array {
                    $dimension = strtolower((string) data_get($label, 'dimension'));

                    return [
                        'dimension' => $dimension,
                        'label'     => (string) data_get($label, 'label', strtoupper($dimension)),
                        'average'   => (float) ($averageByDimension[$dimension] ?? 0),
                    ];
                })
                ->filter(fn (array $item): bool => in_array($item['dimension'], ['a', 'b', 'c', 'd', 'e'], true))
                ->values()
                ->all();

            return [
                'total'                   => (int) ($reviewStat->number_reviews ?? 0),
                'average_rating'          => (float) ($reviewStat->average_rating_main ?? 0),
                'likes'                   => (int) ((clone $reviewQuery)->sum('likes')),
                'status_approved'         => (int) ($reviewStat->number_reviews_approved ?? 0),
                'status_pending'          => (int) ($reviewStat->number_reviews_pending ?? 0),
                'status_rejected'         => (int) ($reviewStat->number_reviews_rejected ?? 0),
                'number_reviews_rating_1' => (int) ($reviewStat->number_rating_1 ?? 0),
                'number_reviews_rating_2' => (int) ($reviewStat->number_rating_2 ?? 0),
                'number_reviews_rating_3' => (int) ($reviewStat->number_rating_3 ?? 0),
                'number_reviews_rating_4' => (int) ($reviewStat->number_rating_4 ?? 0),
                'number_reviews_rating_5' => (int) ($reviewStat->number_rating_5 ?? 0),
                'category_ratings'        => $categoryRatings,
            ];
        }

        $total = (clone $reviewQuery)->count();

        $statusCounts = (clone $reviewQuery)
            ->selectRaw('review_status, count(*) as aggregate')
            ->groupBy('review_status')
            ->pluck('aggregate', 'review_status');

        return [
            'total'                   => $total,
            'average_rating'          => round((float) ((clone $reviewQuery)->avg('rating_main') ?? 0), 1),
            'likes'                   => (int) ((clone $reviewQuery)->sum('likes')),
            'status_approved'         => (int) ($statusCounts[ReviewStatusEnum::APPROVED->value] ?? 0),
            'status_pending'          => (int) ($statusCounts[ReviewStatusEnum::PENDING->value] ?? 0),
            'status_rejected'         => (int) ($statusCounts[ReviewStatusEnum::REJECTED->value] ?? 0),
            'number_reviews_rating_1' => (clone $reviewQuery)->where('rating_main', 1)->count(),
            'number_reviews_rating_2' => (clone $reviewQuery)->where('rating_main', 2)->count(),
            'number_reviews_rating_3' => (clone $reviewQuery)->where('rating_main', 3)->count(),
            'number_reviews_rating_4' => (clone $reviewQuery)->where('rating_main', 4)->count(),
            'number_reviews_rating_5' => (clone $reviewQuery)->where('rating_main', 5)->count(),
            'category_ratings'        => [],
        ];
    }
}
