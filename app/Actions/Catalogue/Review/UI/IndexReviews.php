<?php

namespace App\Actions\Catalogue\Review\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewContextEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ReviewRatingLabel;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReviews extends OrgAction
{
    public function getStats(ProductCategory|Product $parent): array
    {
        $reviewStat = $parent->reviewStats()->first();
        $ratingLabels = ReviewRatingLabel::query()
            ->whereRaw('LOWER(model_type) = ?', ['shop'])
            ->where('model_id', $parent->shop_id)
            ->whereRaw('LOWER(review_context) = ?', [$this->reviewContext($parent)->value])
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

        $reviewModel = $this->reviewModel($parent);
        $foreignKey = $this->foreignKey($parent);

        return [
            'total' => (int) ($reviewStat?->number_reviews ?? 0),
            'average_rating' => (float) ($reviewStat?->average_rating_main ?? 0),
            'verified' => 0,
            'like_count' => (int) $reviewModel::query()->where($foreignKey, $parent->id)->sum('like_count'),
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

    public function handle(ProductCategory|Product $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $reviewModel = $this->reviewModel($parent);
        $table = (new $reviewModel())->getTable();
        $foreignKey = $this->foreignKey($parent);

        return QueryBuilder::for($reviewModel)
            ->with(['media', 'replies'])
            ->leftJoin('customers', 'customers.id', '=', $table . '.customer_id')
            ->where($table . '.' . $foreignKey, $parent->id)
            ->defaultSort('-' . $table . '.created_at')
            ->select([
                $table . '.id',
                $table . '.customer_id',
                $table . '.status',
                $table . '.rating_main as rating',
                $table . '.rating_a',
                $table . '.rating_b',
                $table . '.rating_c',
                $table . '.rating_d',
                $table . '.rating_e',
                $table . '.message',
                $table . '.like_count',
                $table . '.created_at',
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

    public function inProduct(Product $parent, ?string $prefix = null): LengthAwarePaginator
    {
        return $this->handle($parent, $prefix);
    }

    public function tableStructure(ProductCategory|Product $parent, ?string $prefix = null): Closure
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
            $table->column(key: 'reply_status', label: __('Reply Status'), sortable: false, searchable: false, align: 'center');
            $table->column(key: 'like_count', label: __('Like'), sortable: true, searchable: false, align: 'right');
            $table->column(key: 'action', label: __('Actions'), sortable: false, searchable: false, align: 'right');
        };
    }

    private function reviewModel(ProductCategory|Product $parent): string
    {
        return $parent instanceof Product ? ProductReview::class : ProductCategoryReview::class;
    }

    private function foreignKey(ProductCategory|Product $parent): string
    {
        return $parent instanceof Product ? 'product_id' : 'product_category_id';
    }

    private function reviewContext(ProductCategory|Product $parent): ReviewContextEnum
    {
        return $parent instanceof Product ? ReviewContextEnum::ProductReviews : ReviewContextEnum::ProductCategoryReviews;
    }
}
