<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Traits;

use App\Enums\Catalogue\Product\ProductBadgeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithNewInFamiliesQuery
{
    /**
     * The families of the website's shop with at least one product inside its NEW IN period that a
     * customer can see (for sale, priced, on a live webpage), newest arrival first. An existing family
     * qualifies through a single new product, and stays while any of its products is still new.
     */
    public function getNewInFamilies(Webpage $webpage): Builder
    {
        $newArrivals = DB::table('products')
            ->join('webpages as product_webpages', 'product_webpages.id', '=', 'products.webpage_id')
            ->where('products.shop_id', $webpage->shop_id)
            ->where('products.first_available_at', '>', ProductBadgeEnum::newInSince())
            ->where('products.is_for_sale', true)
            ->where('products.price', '>', 0)
            ->where('product_webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNotNull('products.family_id')
            ->groupBy('products.family_id')
            ->selectRaw('products.family_id, MAX(products.first_available_at) as newest_arrival_at');

        return DB::table('product_categories')
            ->joinSub($newArrivals, 'new_arrivals', 'new_arrivals.family_id', '=', 'product_categories.id')
            ->join('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(['product_categories.code', 'product_categories.name', 'product_categories.image_id', 'product_categories.web_images', 'product_categories.offers_data', 'webpages.url', 'webpages.canonical_url', 'title'])
            ->selectRaw('\''.request()->path().'\' as parent_url')
            ->where('product_categories.shop_id', $webpage->shop_id)
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])
            ->where('show_in_website', true)
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at')
            ->whereNull('webpages.deleted_at')
            ->orderByDesc('new_arrivals.newest_arrival_at')
            ->orderBy('product_categories.name');
    }
}
