<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:02:06 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */


use App\Actions\Dropshipping\Shopify\Product\GetApiProductsFromShopify;
use Illuminate\Support\Facades\Route;

Route::name('dropshipping.')->prefix('dropshipping')->group(function () {
    Route::post('shopify-user/{shopifyUser:id}/sync-products', GetApiProductsFromShopify::class)->name('shopify_user.product.sync')->withoutScopedBindings();
});
