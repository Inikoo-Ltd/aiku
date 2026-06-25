<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 May 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IndexReviewOrderInOrder extends OrgAction
{
    public function handle(Order $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $customerId = (int) $parent->customer_id;

        return QueryBuilder::for(Shop::class)
            ->where('shops.id', $parent->shop_id)
            ->leftJoin('shop_reviews', function ($join) use ($parent, $customerId) {
                $join->on('shop_reviews.shop_id', '=', 'shops.id')
                    ->whereNull('shop_reviews.deleted_at')
                    ->where('shop_reviews.order_id', $parent->id)
                    ->where('shop_reviews.customer_id', $customerId);
            })
            ->leftJoin('media as shop_media', 'shop_media.id', '=', 'shops.image_id')
            ->leftJoin('media as shop_review_media', function ($join) {
                $join->on('shop_review_media.model_id', '=', 'shop_reviews.id')
                    ->where('shop_review_media.model_type', '=', 'App\Models\Reviews\Review')
                    ->where('shop_review_media.collection_name', '=', 'review_images');
            })
            ->select([
                'shops.id as reviewable_id',
                DB::raw("'Shop' as reviewable_type"),
                'shops.code as asset_code',
                'shops.name as asset_name',
                DB::raw('NULL as price'),
                DB::raw('NULL as quantity_ordered'),
                DB::raw((int) $parent->id . ' as order_id'),
                DB::raw("CASE WHEN shop_media.id IS NOT NULL THEN json_build_object('id', shop_media.id, 'uuid', shop_media.uuid, 'disk', shop_media.disk, 'conversions_disk', shop_media.conversions_disk, 'file_name', shop_media.file_name, 'mime_type', shop_media.mime_type, 'is_animated', shop_media.is_animated, 'slug', shop_media.slug, 'name', shop_media.name, 'size', shop_media.size, 'generated_conversions', shop_media.generated_conversions) END as row_image_data"),
                DB::raw('MAX(shop_reviews.id) as review_id'),
                DB::raw('MAX(shop_reviews.status) as review_status'),
                DB::raw('MAX(shop_reviews.rating_main) as review_rating'),
                DB::raw('MAX(shop_reviews.rating_a) as review_rating_a'),
                DB::raw('MAX(shop_reviews.rating_b) as review_rating_b'),
                DB::raw('MAX(shop_reviews.rating_c) as review_rating_c'),
                DB::raw('MAX(shop_reviews.rating_d) as review_rating_d'),
                DB::raw('MAX(shop_reviews.rating_e) as review_rating_e'),
                DB::raw('MAX(shop_reviews.message) as review_message'),
                DB::raw("bool_or((shop_reviews.meta->>'is_public')::boolean) as review_is_public"),
                DB::raw("json_agg(json_build_object('id', shop_review_media.id, 'uuid', shop_review_media.uuid, 'disk', shop_review_media.disk, 'conversions_disk', shop_review_media.conversions_disk, 'file_name', shop_review_media.file_name, 'mime_type', shop_review_media.mime_type, 'is_animated', shop_review_media.is_animated, 'slug', shop_review_media.slug, 'name', shop_review_media.name, 'size', shop_review_media.size, 'generated_conversions', shop_review_media.generated_conversions)) FILTER (WHERE shop_review_media.id IS NOT NULL) as review_media_data"),
            ])
            ->groupBy([
                'shops.id',
                'shops.code',
                'shops.name',
                'shop_media.id',
            ])
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
                ->withEmptyState([
                    'title' => __('No order review found'),
                ]);

            $table->column(key: 'image', label: '', canBeHidden: false, sortable: false, searchable: false);
            $table->column(key: 'asset_name', label: __('Name'), canBeHidden: false, sortable: false, searchable: false);
            $table->column(key: 'review_rating', label: __('Rating'), sortable: false, searchable: false, align: 'right', type: 'number');
        };
    }
}
