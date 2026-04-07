<?php

namespace App\Actions\Catalogue\Review\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Review;
use App\Models\Catalogue\ReviewableRatingStat;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReviews extends OrgAction
{
    public function getStats(ProductCategory $parent): array
    {
        $reviewableStat = ReviewableRatingStat::query()
            ->where('reviewable_type', $parent->getMorphClass())
            ->where('reviewable_id', $parent->id)
            ->first();

        return [
            'total' => (int) ($reviewableStat?->reviews_count ?? 0),
            'average_rating' => (float) ($reviewableStat?->rating_average ?? 0),
            'verified' => (int) ($reviewableStat?->verified_reviews_count ?? 0),
            'like_count' => (int) ($reviewableStat?->number_reviews_like ?? 0),
            'status_approved' => (int) ($reviewableStat?->number_reviews_state_approved ?? 0),
            'status_pending' => (int) ($reviewableStat?->number_reviews_state_pending ?? 0),
            'status_rejected' => (int) ($reviewableStat?->number_reviews_state_rejected ?? 0),
            'number_reviews_rating_1' => (int) ($reviewableStat?->number_reviews_rating_1 ?? 0),
            'number_reviews_rating_2' => (int) ($reviewableStat?->number_reviews_rating_2 ?? 0),
            'number_reviews_rating_3' => (int) ($reviewableStat?->number_reviews_rating_3 ?? 0),
            'number_reviews_rating_4' => (int) ($reviewableStat?->number_reviews_rating_4 ?? 0),
            'number_reviews_rating_5' => (int) ($reviewableStat?->number_reviews_rating_5 ?? 0),
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

        return QueryBuilder::for(Review::class)
            ->with(['media.media'])
            ->leftJoin('customers', 'customers.id', '=', 'reviews.customer_id')
            ->where('reviews.reviewable_type', $parent->getMorphClass())
            ->where('reviews.reviewable_id', $parent->id)
            ->defaultSort('-reviews.created_at')
            ->select([
                'reviews.id',
                'reviews.status',
                'reviews.rating',
                'reviews.message',
                'reviews.like_count',
                'reviews.created_at',
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
