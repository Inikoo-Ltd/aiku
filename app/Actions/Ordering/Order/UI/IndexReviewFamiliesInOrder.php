<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 May 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReviewFamiliesInOrder extends OrgAction
{
    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('product_categories.code', $value)
                    ->orWhereAnyWordStartWith('product_categories.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        return QueryBuilder::for(Transaction::class)
            ->where('transactions.order_id', $order->id)
            ->where('transactions.model_type', 'Product')
            ->leftJoin('product_categories', 'transactions.family_id', '=', 'product_categories.id')
            ->leftJoin('reviews', function ($join) use ($order) {
                $join->on('reviews.product_category_id', '=', 'product_categories.id')
                    ->where('reviews.scope', ReviewScopeEnum::FAMILY->value)
                    ->whereNull('reviews.deleted_at')
                    ->where('reviews.order_id', $order->id);
            })->defaultSort('product_categories.code')
            ->select(
                [
                    'product_categories.id as reviewable_id',
                    DB::raw("'".ReviewScopeEnum::FAMILY->value."' as reviewable_type"),
                    'transactions.order_id as order_id',
                    'product_categories.slug as family_slug',
                    'product_categories.code as family_code',
                    'product_categories.name as family_name',
                    'reviews.id as review_id',
                    DB::raw('reviews.rating_main as review_rating'),
                    DB::raw('reviews.rating_a as review_rating_a'),
                    DB::raw('reviews.rating_b as review_rating_b'),
                    DB::raw('reviews.rating_c as review_rating_c'),
                    DB::raw('reviews.rating_d as review_rating_d'),
                    DB::raw('reviews.rating_e as review_rating_e'),
                    DB::raw('reviews.message as review_message'),
                    DB::raw("reviews.is_public as review_is_public"),
                ]
            )->groupBy([
                'product_categories.id',
                'transactions.order_id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'reviews.id',
                'reviews.rating_main',
                'reviews.rating_a',
                'reviews.rating_b',
                'reviews.rating_c',
                'reviews.rating_d',
                'reviews.rating_e',
                'reviews.message',
                'reviews.is_public',
            ])
            ->allowedSorts(['product_categories.code', 'family_name', 'review_rating'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No families found'),
                ])
                ->defaultSort('family_code');

            $table->column(key: 'family_name', label: __('Family'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'review_rating', label: __('Rating'), sortable: true, type: 'number', align: 'right');
        };
    }
}
