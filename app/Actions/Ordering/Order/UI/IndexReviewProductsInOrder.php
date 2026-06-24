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
            ->leftJoin('product_reviews', function ($join) use ($parent, $customerId) {
                $join->on('product_reviews.product_id', '=', 'products.id')
                    ->whereNull('product_reviews.deleted_at')
                    ->where('product_reviews.order_id', $parent->id)
                    ->where('product_reviews.customer_id', $customerId);
            })
            ->leftJoin('media as product_media', 'product_media.id', '=', 'products.image_id')
            ->leftJoin('media as product_review_media', function ($join) {
                $join->on('product_review_media.model_id', '=', 'product_reviews.id')
                    ->where('product_review_media.model_type', '=', 'App\Models\Reviews\ProductReview')
                    ->where('product_review_media.collection_name', '=', 'review_images');
            })
            ->defaultSort('asset_code')
            ->select([
                'products.id as reviewable_id',
                DB::raw("'Product' as reviewable_type"),
                'products.slug as product_slug',
                'products.code as asset_code',
                'products.name as asset_name',
                'products.price as price',
                'transactions.order_id as order_id',
                DB::raw("CASE WHEN product_media.id IS NOT NULL THEN json_build_object('id', product_media.id, 'uuid', product_media.uuid, 'disk', product_media.disk, 'conversions_disk', product_media.conversions_disk, 'file_name', product_media.file_name, 'mime_type', product_media.mime_type, 'is_animated', product_media.is_animated, 'slug', product_media.slug, 'name', product_media.name, 'size', product_media.size, 'generated_conversions', product_media.generated_conversions) END as row_image_data"),
                DB::raw('SUM(transactions.quantity_ordered) as quantity_ordered'),
                DB::raw('MAX(product_reviews.id) as review_id'),
                DB::raw('MAX(product_reviews.status) as review_status'),
                DB::raw('MAX(product_reviews.rating_main) as review_rating'),
                DB::raw('MAX(product_reviews.rating_a) as review_rating_a'),
                DB::raw('MAX(product_reviews.rating_b) as review_rating_b'),
                DB::raw('MAX(product_reviews.rating_c) as review_rating_c'),
                DB::raw('MAX(product_reviews.rating_d) as review_rating_d'),
                DB::raw('MAX(product_reviews.rating_e) as review_rating_e'),
                DB::raw('MAX(product_reviews.message) as review_message'),
                DB::raw("bool_or((product_reviews.meta->>'is_public')::boolean) as review_is_public"),
                DB::raw("json_agg(json_build_object('id', product_review_media.id, 'uuid', product_review_media.uuid, 'disk', product_review_media.disk, 'conversions_disk', product_review_media.conversions_disk, 'file_name', product_review_media.file_name, 'mime_type', product_review_media.mime_type, 'is_animated', product_review_media.is_animated, 'slug', product_review_media.slug, 'name', product_review_media.name, 'size', product_review_media.size, 'generated_conversions', product_review_media.generated_conversions)) FILTER (WHERE product_review_media.id IS NOT NULL) as review_media_data"),
            ])
            ->groupBy([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.price',
                'product_media.id',
                'transactions.order_id',
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

            $table->column(key: 'image', label: '', canBeHidden: false, sortable: false, searchable: false);
            $table->column(key: 'asset_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_ordered', label: __('Quantity'), canBeHidden: false, sortable: false, searchable: false, type: 'number');
            $table->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: false, searchable: false, type: 'currency');
            $table->column(key: 'review_rating', label: __('Rating'), sortable: true, searchable: false, align: 'right', type: 'number');
        };
    }
}
