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

class IndexReviewFamiliesInOrder extends OrgAction
{
    public function handle(Order $parent, $prefix = null): LengthAwarePaginator
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

        $customerId = (int) $parent->customer_id;

        return QueryBuilder::for(Transaction::class)
            ->where('transactions.order_id', $parent->id)
            ->where('transactions.model_type', 'Product')
            ->leftJoin('products', 'transactions.model_id', '=', 'products.id')
            ->join('product_categories', 'products.family_id', '=', 'product_categories.id')
            ->leftJoin('product_category_reviews', function ($join) use ($parent, $customerId) {
                $join->on('product_category_reviews.product_category_id', '=', 'product_categories.id')
                    ->whereNull('product_category_reviews.deleted_at')
                    ->where('product_category_reviews.order_id', $parent->id)
                    ->where('product_category_reviews.customer_id', $customerId);
            })
            ->leftJoin('media as family_media', 'family_media.id', '=', 'product_categories.image_id')
            ->leftJoin('media as family_review_media', function ($join) {
                $join->on('family_review_media.model_id', '=', 'product_category_reviews.id')
                    ->where('family_review_media.model_type', '=', 'App\Models\Reviews\ProductCategoryReview')
                    ->where('family_review_media.collection_name', '=', 'review_images');
            })
            ->defaultSort('asset_code')
            ->select([
                'product_categories.id as reviewable_id',
                DB::raw("'ProductCategory' as reviewable_type"),
                'product_categories.slug as product_slug',
                'product_categories.code as asset_code',
                'product_categories.name as asset_name',
                DB::raw('NULL as price'),
                'transactions.order_id as order_id',
                DB::raw("CASE WHEN family_media.id IS NOT NULL THEN json_build_object('id', family_media.id, 'uuid', family_media.uuid, 'disk', family_media.disk, 'conversions_disk', family_media.conversions_disk, 'file_name', family_media.file_name, 'mime_type', family_media.mime_type, 'is_animated', family_media.is_animated, 'slug', family_media.slug, 'name', family_media.name, 'size', family_media.size, 'generated_conversions', family_media.generated_conversions) END as row_image_data"),
                DB::raw('SUM(transactions.quantity_ordered) as quantity_ordered'),
                DB::raw('MAX(product_category_reviews.id) as review_id'),
                DB::raw('MAX(product_category_reviews.status) as review_status'),
                DB::raw('MAX(product_category_reviews.rating_main) as review_rating'),
                DB::raw('MAX(product_category_reviews.rating_a) as review_rating_a'),
                DB::raw('MAX(product_category_reviews.rating_b) as review_rating_b'),
                DB::raw('MAX(product_category_reviews.rating_c) as review_rating_c'),
                DB::raw('MAX(product_category_reviews.rating_d) as review_rating_d'),
                DB::raw('MAX(product_category_reviews.rating_e) as review_rating_e'),
                DB::raw('MAX(product_category_reviews.message) as review_message'),
                DB::raw("bool_or((product_category_reviews.meta->>'is_public')::boolean) as review_is_public"),
                DB::raw("json_agg(json_build_object('id', family_review_media.id, 'uuid', family_review_media.uuid, 'disk', family_review_media.disk, 'conversions_disk', family_review_media.conversions_disk, 'file_name', family_review_media.file_name, 'mime_type', family_review_media.mime_type, 'is_animated', family_review_media.is_animated, 'slug', family_review_media.slug, 'name', family_review_media.name, 'size', family_review_media.size, 'generated_conversions', family_review_media.generated_conversions)) FILTER (WHERE family_review_media.id IS NOT NULL) as review_media_data"),
            ])
            ->groupBy([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'family_media.id',
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
                    'title' => __('No families found'),
                ])
                ->defaultSort('asset_code');

            $table->column(key: 'image', label: '', canBeHidden: false, sortable: false, searchable: false);
            $table->column(key: 'asset_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_ordered', label: __('Quantity'), canBeHidden: false, sortable: false, searchable: false, type: 'number');
            $table->column(key: 'review_rating', label: __('Rating'), sortable: true, searchable: false, align: 'right', type: 'number');
        };
    }
}
