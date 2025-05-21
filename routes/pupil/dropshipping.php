<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 19-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

use App\Actions\Pupil\IndexPupilPlatformCustomerClients;
use App\Actions\Retina\Dropshipping\Client\FetchRetinaCustomerClientFromShopify;
use App\Actions\Retina\Dropshipping\Orders\IndexRetinaDropshippingOrdersInPlatform;
use App\Actions\Retina\Dropshipping\Portfolio\IndexRetinaPortfolios;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaProductsInDropshipping;
use Illuminate\Support\Facades\Route;

Route::prefix('platforms/{platform}')->as('platforms.')->group(function () {
    Route::prefix('client')->as('client.')->group(function () {
        Route::get('/', IndexPupilPlatformCustomerClients::class)->name('index');
        Route::get('fetch', [FetchRetinaCustomerClientFromShopify::class, 'inPupil'])->name('fetch');
    });

    Route::prefix('portfolios')->as('portfolios.')->group(function () {
        Route::get('my-portfolio', [IndexRetinaPortfolios::class, 'inPupil'])->name('index');
        Route::get('products', [IndexRetinaProductsInDropshipping::class, 'inPupil'])->name('products.index');
    });

    Route::prefix('orders')->as('orders.')->group(function () {
        Route::get('/', [IndexRetinaDropshippingOrdersInPlatform::class, 'inPupil'])->name('index');
    });
});
