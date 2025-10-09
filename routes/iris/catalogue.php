<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 15 Aug 2025 08:06:50 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


use App\Actions\Iris\Catalogue\DownloadIrisProduct;

Route::prefix('feeds')->as('feeds.')->group(function () {
    Route::get('product-category/{productCategory}/download.csv', [DownloadIrisProduct::class, 'inProductCategory'])->name('product_category.download');
    Route::get('shop/{shop}/download.csv', [DownloadIrisProduct::class, 'inShop'])->name('shop.download');
    Route::get('product/{product}/download.csv', [DownloadIrisProduct::class, 'inProduct'])->name('product.download');
});
