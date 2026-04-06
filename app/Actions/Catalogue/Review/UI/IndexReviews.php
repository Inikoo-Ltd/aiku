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
            'helpful_count' => (int) (Review::query()
                ->where('reviewable_type', $parent->getMorphClass())
                ->where('reviewable_id', $parent->id)
                ->sum('helpful_count') ?? 0),
            'status_approved' => (int) (Review::query()
                ->where('reviewable_type', $parent->getMorphClass())
                ->where('reviewable_id', $parent->id)
                ->where('status', 'approved')
                ->count()),
            'status_pending' => (int) (Review::query()
                ->where('reviewable_type', $parent->getMorphClass())
                ->where('reviewable_id', $parent->id)
                ->where('status', 'pending')
                ->count()),
            'status_rejected' => (int) (Review::query()
                ->where('reviewable_type', $parent->getMorphClass())
                ->where('reviewable_id', $parent->id)
                ->where('status', 'rejected')
                ->count()),
            'rating_breakdown' => $reviewableStat?->rating_breakdown ?? [],
        ];
    }

    public function handle(ProductCategory $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('reviews.title', $value)
                    ->orWhereAnyWordStartWith('reviews.message', $value)
                    ->orWhereAnyWordStartWith('customers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Review::class)
            ->leftJoin('customers', 'customers.id', '=', 'reviews.customer_id')
            ->where('reviews.reviewable_type', $parent->getMorphClass())
            ->where('reviews.reviewable_id', $parent->id)
            ->defaultSort('-reviews.created_at')
            ->select([
                'reviews.id',
                'reviews.status',
                'reviews.rating',
                'reviews.title',
                'reviews.message',
                'reviews.is_verified_purchase',
                'reviews.helpful_count',
                'reviews.created_at',
                'customers.name as customer_name',
            ])
            ->allowedSorts(['id', 'created_at', 'rating', 'helpful_count'])
            ->allowedFilters([$globalSearch, 'status', 'rating', 'is_verified_purchase'])
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
                ->defaultSort('created_at')
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No reviews found'),
                    'count' => 0,
                ]);

            $table->column(key: 'created_at', label: __('Created'), sortable: true, type: 'date');
            $table->column(key: 'customer_name', label: __('Customer'), sortable: false, searchable: true);
            $table->column(key: 'status', label: __('Status'), sortable: true, searchable: true);
            $table->column(key: 'rating', label: __('Rating'), sortable: true, searchable: false, align: 'right');
            $table->column(key: 'title', label: __('Title'), sortable: false, searchable: true);
            $table->column(key: 'helpful_count', label: __('Helpful'), sortable: true, searchable: false, align: 'right');
        };
    }
}
