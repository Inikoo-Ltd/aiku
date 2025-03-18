<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 19-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

use App\Actions\Retina\Dropshipping\Client\FetchRetinaCustomerClientFromShopify;
use App\Actions\Retina\Dropshipping\Client\UI\IndexRetinaPlatformCustomerClients;
use App\Actions\Retina\Dropshipping\Orders\IndexRetinaPlatformDropshippingOrders;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaDropshippingPortfolio;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaDropshippingProducts;
use Illuminate\Support\Facades\Route;

Route::prefix('platforms/{platform}')->as('platforms.')->group(function () {
    Route::prefix('client')->as('client.')->group(function () {
        Route::get('/', [IndexRetinaPlatformCustomerClients::class, 'inPupil'])->name('index');
        Route::get('fetch', [FetchRetinaCustomerClientFromShopify::class, 'inPupil'])->name('fetch');
    });

    Route::prefix('portfolios')->as('portfolios.')->group(function () {
        Route::get('my-portfolio', [IndexRetinaDropshippingPortfolio::class, 'inPupil'])->name('index');
        Route::get('products', [IndexRetinaDropshippingProducts::class, 'inPupil'])->name('products.index');
    });

    Route::prefix('orders')->as('orders.')->group(function () {
        Route::get('/', [IndexRetinaPlatformDropshippingOrders::class, 'inPupil'])->name('index');
    });
});
