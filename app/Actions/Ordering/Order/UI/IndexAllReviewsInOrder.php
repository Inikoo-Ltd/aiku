<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 19:52:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Media;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAllReviewsInOrder extends OrgAction
{
    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereAnyWordStartWith('product_categories.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $paginator = QueryBuilder::for(Review::class)
            ->where('reviews.order_id', $order->id)
            ->leftJoin('products', 'reviews.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'reviews.product_category_id', '=', 'product_categories.id')
            ->leftJoin('shops', 'reviews.shop_id', '=', 'shops.id')
            ->select([
                'reviews.id as review_id',
                'reviews.scope',
                'reviews.rating_main as review_rating',
                'reviews.rating_a as review_rating_a',
                'reviews.rating_b as review_rating_b',
                'reviews.rating_c as review_rating_c',
                'reviews.rating_d as review_rating_d',
                'reviews.rating_e as review_rating_e',
                'reviews.message as review_message',
                'reviews.is_public as review_is_public',
                'reviews.review_status',
                'reviews.created_at',
                'reviews.replied',
                'reviews.reply_message',
                'reviews.reply_at',
                'reviews.likes',
                'reviews.dislikes',
                'reviews.replay_likes',
                'reviews.replay_dislikes',
                'products.code as asset_code',
                'products.name as asset_name',
                'product_categories.code as family_code',
                'product_categories.name as family_name',
                'shops.name as shop_name',
            ])
            ->defaultSort('-reviews.created_at')
            ->allowedSorts(['review_rating', 'reviews.created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $reviewIds = collect($paginator->items())->pluck('review_id')->filter()->unique()->values();

        $mediaByReviewId = $reviewIds->isNotEmpty()
            ? Media::query()
                ->where('model_type', (new Review())->getMorphClass())
                ->whereIn('model_id', $reviewIds)
                ->where('collection_name', 'review_images')
                ->get()
                ->groupBy('model_id')
            : collect();

        return $paginator->through(function ($item) use ($mediaByReviewId) {
            $item->review_images = $mediaByReviewId->get($item->review_id, collect());
            return $item;
        });
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No reviews found'),
                ])
                ->defaultSort('-reviews.created_at');

            $table->column(key: 'scope', label: __('Type'), canBeHidden: false);
            $table->column(key: 'name', label: __('Name'), canBeHidden: false, searchable: true);
            $table->column(key: 'review_rating', label: __('Rating'), sortable: true, type: 'number', align: 'right');
            $table->column(key: 'review_message', label: __('Review'), canBeHidden: false);
        };
    }
}
