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

class IndexReviewProductsInOrder extends OrgAction
{
    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('products.code', $value)
                    ->orWhereAnyWordStartWith('products.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Transaction::class)
            ->where('transactions.order_id', $order->id)
            ->where('transactions.model_type', 'Product')
            ->leftJoin('products', 'transactions.model_id', '=', 'products.id')
            ->leftJoin('reviews', function ($join) use ($order) {
                $join->on('reviews.product_id', '=', 'products.id')
                    ->whereNull('reviews.deleted_at')
                    ->where('reviews.order_id', $order->id);
            })

            ->defaultSort('products.code')
            ->select([
                'products.id as reviewable_id',
                DB::raw("'".ReviewScopeEnum::PRODUCT->value."' as reviewable_type"),
                'products.slug as product_slug',
                'products.code as asset_code',
                'products.name as asset_name',
                'products.price as price',
                'transactions.order_id as order_id',
                DB::raw('transactions.quantity_ordered as quantity_ordered'),
                DB::raw('reviews.id as review_id'),
                DB::raw('reviews.rating_main as review_rating'),
                DB::raw('reviews.rating_a as review_rating_a'),
                DB::raw('reviews.rating_b as review_rating_b'),
                DB::raw('reviews.rating_c as review_rating_c'),
                DB::raw('reviews.rating_d as review_rating_d'),
                DB::raw('reviews.rating_e as review_rating_e'),
                DB::raw('reviews.message as review_message'),
                DB::raw("reviews.is_public as review_is_public"),
            ])

            ->allowedSorts(['asset_code', 'asset_name', 'review_rating'])
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
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No products found'),
                ])
                ->defaultSort('asset_code');

            $table->column(key: 'asset_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_ordered', label: __('Quantity'), canBeHidden: false, type: 'number');
            $table->column(key: 'review_rating', label: __('Rating'), sortable: true, type: 'number', align: 'right');
        };
    }
}
