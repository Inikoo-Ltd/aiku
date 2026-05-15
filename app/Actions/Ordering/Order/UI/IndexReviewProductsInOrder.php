<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 May 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
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
    public function handle(Order $parent, $prefix = null): LengthAwarePaginator
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

        $customerId = (int) $parent->customer_id;

        return QueryBuilder::for(Transaction::class)
            ->where('transactions.order_id', $parent->id)
            ->where('transactions.model_type', 'Product')
            ->leftJoin('products', 'transactions.model_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.family_id', '=', 'product_categories.id')
            ->leftJoin('product_reviews', function ($join) use ($parent, $customerId) {
                $join->on('product_reviews.product_id', '=', 'products.id')
                    ->whereNull('product_reviews.deleted_at')
                    ->where('product_reviews.order_id', $parent->id)
                    ->where('product_reviews.customer_id', $customerId);
            })
            ->leftJoin('product_category_reviews', function ($join) use ($parent, $customerId) {
                $join->on('product_category_reviews.product_category_id', '=', 'products.family_id')
                    ->whereNull('product_category_reviews.deleted_at')
                    ->where('product_category_reviews.order_id', $parent->id)
                    ->where('product_category_reviews.customer_id', $customerId);
            })
            ->leftJoin('shop_reviews', function ($join) use ($parent, $customerId) {
                $join->on('shop_reviews.shop_id', '=', 'transactions.shop_id')
                    ->whereNull('shop_reviews.deleted_at')
                    ->where('shop_reviews.order_id', $parent->id)
                    ->where('shop_reviews.customer_id', $customerId);
            })
            ->defaultSort('products.code')
            ->select([
                'products.id as id',
                'products.id as product_id',
                'products.slug as product_slug',
                'products.code as product_code',
                'products.name as product_name',
                'products.image_id as product_image_id',
                'products.code as asset_code',
                'products.name as asset_name',
                'products.price as price',
                'products.family_id as product_category_id',
                'product_categories.name as product_category_name',
                'transactions.order_id as order_id',
                'transactions.shop_id as shop_id',
                'transactions.net_amount as net_amount',
                DB::raw('SUM(transactions.quantity_ordered) as quantity_ordered'),

                DB::raw('MAX(product_reviews.id) as product_review_id'),
                DB::raw('MAX(product_reviews.status) as product_review_status'),
                DB::raw('MAX(product_reviews.rating_main) as product_review_rating'),
                DB::raw('MAX(product_reviews.rating_a) as product_review_rating_a'),
                DB::raw('MAX(product_reviews.rating_b) as product_review_rating_b'),
                DB::raw('MAX(product_reviews.rating_c) as product_review_rating_c'),
                DB::raw('MAX(product_reviews.rating_d) as product_review_rating_d'),
                DB::raw('MAX(product_reviews.rating_e) as product_review_rating_e'),
                DB::raw('MAX(product_reviews.message) as product_review_message'),

                DB::raw('MAX(product_category_reviews.id) as product_category_review_id'),
                DB::raw('MAX(product_category_reviews.status) as product_category_review_status'),
                DB::raw('MAX(product_category_reviews.rating_main) as product_category_review_rating'),
                DB::raw('MAX(product_category_reviews.rating_a) as product_category_review_rating_a'),
                DB::raw('MAX(product_category_reviews.rating_b) as product_category_review_rating_b'),
                DB::raw('MAX(product_category_reviews.rating_c) as product_category_review_rating_c'),
                DB::raw('MAX(product_category_reviews.rating_d) as product_category_review_rating_d'),
                DB::raw('MAX(product_category_reviews.rating_e) as product_category_review_rating_e'),
                DB::raw('MAX(product_category_reviews.message) as product_category_review_message'),

                DB::raw('MAX(shop_reviews.id) as shop_review_id'),
                DB::raw('MAX(shop_reviews.status) as shop_review_status'),
                DB::raw('MAX(shop_reviews.rating_main) as shop_review_rating'),
                DB::raw('MAX(shop_reviews.rating_a) as shop_review_rating_a'),
                DB::raw('MAX(shop_reviews.rating_b) as shop_review_rating_b'),
                DB::raw('MAX(shop_reviews.rating_c) as shop_review_rating_c'),
                DB::raw('MAX(shop_reviews.rating_d) as shop_review_rating_d'),
                DB::raw('MAX(shop_reviews.rating_e) as shop_review_rating_e'),
                DB::raw('MAX(shop_reviews.message) as shop_review_message'),
            ])
            ->groupBy([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.image_id',
                'products.family_id',
                'products.price',
                'product_categories.name',
                'transactions.order_id',
                'transactions.shop_id',
                'transactions.shop_id',
                'transactions.net_amount'
            ])
            ->allowedSorts(['asset_code', 'asset_name', 'product_review_rating'])
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
                ->defaultSort('product_code');

            $table->column(key: 'image', label: '', canBeHidden: false, sortable: false, searchable: false);
            $table->column(key: 'asset_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_ordered', label: __('Quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
            $table->column(key: 'product_review_rating', label: __('Rating'), sortable: true, searchable: false, align: 'right', type: 'number');
        };
    }
}
