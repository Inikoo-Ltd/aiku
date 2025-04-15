<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 19-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

use App\Actions\Dropshipping\ShopifyUser\DeleteRetinaShopifyUser;
use App\Actions\Dropshipping\ShopifyUser\StoreRetinaShopifyUser;
use App\Actions\Dropshipping\Tiktok\User\AuthenticateTiktokAccount;
use App\Actions\Dropshipping\WooCommerce\AuthorizeRetinaWooCommerceUser;
use App\Actions\Dropshipping\WooCommerce\StoreRetinaWooCommerceUser;
use App\Actions\Retina\Dropshipping\Client\FetchRetinaCustomerClientFromShopify;
use App\Actions\Retina\Dropshipping\Client\UI\CreateRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\Client\UI\IndexRetinaCustomerClients;
use App\Actions\Retina\Dropshipping\Client\UI\IndexRetinaPlatformCustomerClients;
use App\Actions\Retina\Dropshipping\Client\UI\ShowRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\Orders\IndexRetinaDropshippingOrders;
use App\Actions\Retina\Dropshipping\Orders\IndexRetinaPlatformDropshippingOrders;
use App\Actions\Retina\Dropshipping\Orders\ShowRetinaDropshippingOrder;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaDropshippingPortfolio;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaDropshippingProducts;
use App\Actions\Retina\Dropshipping\ShowRetinaDropshipping;
use App\Actions\Retina\Dropshipping\ShowRetinaProduct;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->as('platform.')->group(function () {
    Route::get('/', ShowRetinaDropshipping::class)->name('dashboard');

    Route::post('shopify-user', StoreRetinaShopifyUser::class)->name('shopify_user.store');
    Route::delete('shopify-user', DeleteRetinaShopifyUser::class)->name('shopify_user.delete');

    Route::post('wc-user/authorize', AuthorizeRetinaWooCommerceUser::class)->name('wc.authorize');
    Route::post('wc-user', StoreRetinaWooCommerceUser::class)->name('wc.store');
    Route::delete('wc-user', DeleteRetinaShopifyUser::class)->name('wc.delete');
});

Route::prefix('client')->as('client.')->group(function () {
    Route::get('/', IndexRetinaCustomerClients::class)->name('index');
    Route::get('create', CreateRetinaCustomerClient::class)->name('create');
    Route::get('fetch', FetchRetinaCustomerClientFromShopify::class)->name('fetch');
    Route::get('{customerClient}/show', ShowRetinaCustomerClient::class)->name('show');
});

Route::prefix('portfolios')->as('portfolios.')->group(function () {
    Route::get('my-portfolio', IndexRetinaDropshippingPortfolio::class)->name('index');
    Route::get('my-portfolio/{product}', ShowRetinaProduct::class)->name('show');
    Route::get('products', IndexRetinaDropshippingProducts::class)->name('products.index');
});

Route::prefix('orders')->as('orders.')->group(function () {
    Route::get('/', IndexRetinaDropshippingOrders::class)->name('index');
    Route::get('/{order}', ShowRetinaDropshippingOrder::class)->name('show');
});

Route::prefix('platforms/{platform}')->as('platforms.')->group(function () {
    Route::prefix('client')->as('client.')->group(function () {
        Route::get('/', IndexRetinaPlatformCustomerClients::class)->name('index');
        Route::get('fetch', [FetchRetinaCustomerClientFromShopify::class, 'inPlatform'])->name('fetch');
    });

    Route::prefix('portfolios')->as('portfolios.')->group(function () {
        Route::get('my-portfolio', [IndexRetinaDropshippingPortfolio::class, 'inPlatform'])->name('index');
        Route::get('products', [IndexRetinaDropshippingProducts::class, 'inPlatform'])->name('products.index');
    });

    Route::prefix('orders')->as('orders.')->group(function () {
        Route::get('/', [IndexRetinaPlatformDropshippingOrders::class, 'inPlatform'])->name('index');
        Route::get('/{order}', [ShowRetinaDropshippingOrder::class, 'inPlatform'])->name('show');
    });
});

Route::prefix('tiktok')->name('tiktok.')->group(function () {
    Route::get('callback', AuthenticateTiktokAccount::class)->name('callback');
});


// Route::get('/users', IndexUsers::class)->name('web-users.index');
// Route::get('/users/{user}', ShowUser::class)->name('web-users.show');
// Route::get('/users/{user}/edit', EditUser::class)->name('web-users.edit');
