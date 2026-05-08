<?php

namespace App\Actions\Catalogue\Review\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\ProductCategory;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ReviewRatingLabel;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReviews extends OrgAction
{
    public function getStats(ProductCategory $parent): array
    {
        $reviewStat = $parent->reviewStats()->first();
        $ratingLabels = ReviewRatingLabel::query()
            ->whereRaw('LOWER(model_type) = ?', ['shop'])
            ->where('model_id', $parent->shop_id)
            ->whereRaw('LOWER(review_context) = ?', ['product_category_reviews'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('dimension')
            ->get(['dimension', 'label'])
            ->map(fn (ReviewRatingLabel $reviewRatingLabel): array => [
                'dimension' => $reviewRatingLabel->dimension?->value ?? (string) $reviewRatingLabel->dimension,
                'label' => (string) $reviewRatingLabel->label,
            ])
            ->values()
            ->all();

        $averageByDimension = [
            'a' => round((float) ($reviewStat?->average_rating_a ?? 0), 2),
            'b' => round((float) ($reviewStat?->average_rating_b ?? 0), 2),
            'c' => round((float) ($reviewStat?->average_rating_c ?? 0), 2),
            'd' => round((float) ($reviewStat?->average_rating_d ?? 0), 2),
            'e' => round((float) ($reviewStat?->average_rating_e ?? 0), 2),
        ];

        $categoryRatings = collect($ratingLabels)
            ->map(function (array $label) use ($averageByDimension): array {
                $dimension = strtolower((string) data_get($label, 'dimension'));

                return [
                    'dimension' => $dimension,
                    'label' => (string) data_get($label, 'label', strtoupper($dimension)),
                    'average' => (float) ($averageByDimension[$dimension] ?? 0),
                ];
            })
            ->filter(fn (array $item): bool => in_array($item['dimension'], ['a', 'b', 'c', 'd', 'e'], true))
            ->values()
            ->all();

        return [
            'total' => (int) ($reviewStat?->number_reviews ?? 0),
            'average_rating' => (float) ($reviewStat?->average_rating_main ?? 0),
            'verified' => 0,
            'like_count' => (int) ProductCategoryReview::query()->where('product_category_id', $parent->id)->sum('like_count'),
            'status_approved' => (int) ($reviewStat?->number_reviews_approved ?? 0),
            'status_pending' => (int) ($reviewStat?->number_reviews_pending ?? 0),
            'status_rejected' => (int) ($reviewStat?->number_reviews_rejected ?? 0),
            'number_reviews_rating_1' => (int) ($reviewStat?->number_rating_1 ?? 0),
            'number_reviews_rating_2' => (int) ($reviewStat?->number_rating_2 ?? 0),
            'number_reviews_rating_3' => (int) ($reviewStat?->number_rating_3 ?? 0),
            'number_reviews_rating_4' => (int) ($reviewStat?->number_rating_4 ?? 0),
            'number_reviews_rating_5' => (int) ($reviewStat?->number_rating_5 ?? 0),
            'category_ratings' => $categoryRatings,
        ];
    }

    public function handle(ProductCategory $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(ProductCategoryReview::class)
            ->with(['media'])
            ->leftJoin('customers', 'customers.id', '=', 'product_category_reviews.customer_id')
            ->where('product_category_reviews.product_category_id', $parent->id)
            ->defaultSort('-product_category_reviews.created_at')
            ->select([
                'product_category_reviews.id',
                'product_category_reviews.customer_id',
                'product_category_reviews.status',
                'product_category_reviews.rating_main as rating',
                'product_category_reviews.rating_a',
                'product_category_reviews.rating_b',
                'product_category_reviews.rating_c',
                'product_category_reviews.rating_d',
                'product_category_reviews.rating_e',
                'product_category_reviews.message',
                'product_category_reviews.like_count',
                'product_category_reviews.created_at',
                'customers.contact_name as contact_name',
            ])
            ->allowedSorts(['id', 'created_at', 'rating', 'like_count'])
            ->allowedFilters([$globalSearch, 'status', 'rating', 'contact_name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function inProductCategory(ProductCategory $parent, ?string $prefix = null): LengthAwarePaginator
    {
        return $this->handle($parent, $prefix);
    }

    public function tableStructure(ProductCategory $parent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->defaultSort('like_count')
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No reviews found'),
                    'count' => 0,
                ]);

            $table->column(key: 'created_at', label: __('Created'), sortable: true, type: 'date');
            $table->column(key: 'image_thumbnails', label: __('Images'), sortable: false, searchable: false);
            $table->column(key: 'contact_name', label: __('Name'), sortable: false, searchable: true);
            $table->column(key: 'rating', label: __('Rating'), sortable: true, searchable: false, align: 'right');
            $table->column(key: 'message', label: __('Message'), sortable: false, searchable: true);
            $table->column(key: 'like_count', label: __('Like'), sortable: true, searchable: false, align: 'right');
            $table->column(key: 'action', label: __('Actions'), sortable: false, searchable: false, align: 'right');
        };
    }
}
