<?php

namespace App\Actions\Catalogue\Review\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\Review;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReviews extends OrgAction
{
    public function getStats(Shop $shop): array
    {
        $row = Review::query()
            ->where('shop_id', $shop->id)
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(AVG(rating_main), 0) as average_rating,
                COUNT(*) FILTER (WHERE review_status = ?) as status_approved,
                COUNT(*) FILTER (WHERE review_status = ?) as status_pending,
                COUNT(*) FILTER (WHERE review_status = ?) as status_rejected,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 1) as number_reviews_rating_1,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 2) as number_reviews_rating_2,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 3) as number_reviews_rating_3,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 4) as number_reviews_rating_4,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 5) as number_reviews_rating_5,
                COALESCE(AVG(rating_a), 0) as average_rating_a,
                COALESCE(AVG(rating_b), 0) as average_rating_b,
                COALESCE(AVG(rating_c), 0) as average_rating_c,
                COALESCE(AVG(rating_d), 0) as average_rating_d,
                COALESCE(AVG(rating_e), 0) as average_rating_e
            ',
                [
                    ReviewStatusEnum::APPROVED->value,
                    ReviewStatusEnum::PENDING->value,
                    ReviewStatusEnum::REJECTED->value,
                ]
            )
            ->first();

        return [
            'total'                   => (int)$row->total,
            'average_rating'          => (float)$row->average_rating,
            'status_approved'         => (int)$row->status_approved,
            'status_pending'          => (int)$row->status_pending,
            'status_rejected'         => (int)$row->status_rejected,
            'number_reviews_rating_1' => (int)$row->number_reviews_rating_1,
            'number_reviews_rating_2' => (int)$row->number_reviews_rating_2,
            'number_reviews_rating_3' => (int)$row->number_reviews_rating_3,
            'number_reviews_rating_4' => (int)$row->number_reviews_rating_4,
            'number_reviews_rating_5' => (int)$row->number_reviews_rating_5,
            'average_rating_a'        => (float)$row->average_rating_a,
            'average_rating_b'        => (float)$row->average_rating_b,
            'average_rating_c'        => (float)$row->average_rating_c,
            'average_rating_d'        => (float)$row->average_rating_d,
            'average_rating_e'        => (float)$row->average_rating_e,
        ];
    }

    public function handle(ProductCategory|Product|Shop $parent, ?string $prefix = null, ?string $scope = null, ?string $bucket = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Review::class)
            ->leftJoin('customers', 'customers.id', '=', 'reviews.customer_id');

        if ($parent instanceof Shop) {
            $query->where('reviews.shop_id', $parent->id);
        } elseif ($parent instanceof ProductCategory) {
            $query->where('reviews.product_category_id', $parent->id);
        } elseif ($parent instanceof Product) {
            $query->where('reviews.product_id', $parent->id);
        }

        if ($scope === 'overall') {
            $query->whereIn('scope', [ReviewScopeEnum::SHOP->value, ReviewScopeEnum::ORDER->value]);
        } elseif ($scope === 'family') {
            $query->where('scope', ReviewScopeEnum::FAMILY->value);
        } elseif ($scope === 'product') {
            $query->where('scope', ReviewScopeEnum::PRODUCT->value);
        }

        if ($bucket === 'waiting') {
            $query->where('reviews.review_status', ReviewStatusEnum::PENDING->value);
        }

        return $query->defaultSort('-reviews.created_at')
            ->select([
                'reviews.id',
                'reviews.customer_id',
                'reviews.review_status as status',
                'reviews.state',
                'reviews.rating_main as rating',
                'reviews.rating_a',
                'reviews.rating_b',
                'reviews.rating_c',
                'reviews.rating_d',
                'reviews.rating_e',
                'reviews.message',
                'reviews.likes',
                'reviews.dislikes',
                'reviews.scope',
                'reviews.replied',
                'reviews.reply_message',
                'reviews.reply_at',
                'reviews.meta',
                'reviews.created_at',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
            ])
            ->allowedSorts(['id', 'created_at', 'rating', 'likes'])
            ->allowedFilters([$globalSearch, 'status', 'rating', 'customer_name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('likes')
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No reviews found'),
                    'count' => 0,
                ]);

            $table->column(key: 'state_icon', label: '', type: 'icon');
            $table->column(key: 'created_at', label: __('Date'), sortable: true, type: 'date');
            $table->column(key: 'customer_name', label: __('Customer'), searchable: true);
            $table->column(key: 'rating', label: __('Rating'), sortable: true, align: 'right');
            $table->column(key: 'message', label: __('Message'), searchable: true);
            $table->column(key: 'likes', label: __('Likes / Dislikes'), sortable: true, align: 'right');
            $table->column(key: 'action', label: __('Actions'), align: 'right');
        };
    }
}
