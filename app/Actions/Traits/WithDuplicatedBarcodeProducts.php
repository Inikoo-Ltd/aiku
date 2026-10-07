<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Builder;

/**
 * A shop refuses several listings carrying one GTIN. The same item listed in three pack sizes
 * shares one trade unit, so all three mirror its barcode and all three are correct as data:
 * only a person can say which listing represents the item. This finds the ones still to decide.
 */
trait WithDuplicatedBarcodeProducts
{
    public function duplicatedBarcodeProducts(Shop $shop): Builder
    {
        /*
         * ponytail: the listing is what a shop rejects, so a discontinued, non main or customer
         * exclusive product is not a collision. Same scope on both sides of the subquery.
         */
        $listed = fn ($query) => $query
            ->where('products.shop_id', $shop->id)
            ->where('products.is_main', true)
            ->whereNull('products.exclusive_for_customer_id')
            ->whereNotNull('products.barcode')
            ->where('products.barcode', '<>', '')
            ->where('products.state', '<>', ProductStateEnum::DISCONTINUED->value);

        return Product::where($listed)
            ->whereIn('products.barcode', function ($query) use ($listed) {
                $query->select('barcode')
                    ->from('products')
                    ->whereNull('products.deleted_at')
                    ->where($listed)
                    ->groupBy('products.barcode')
                    ->havingRaw('count(*) > 1');
            });
    }
}
