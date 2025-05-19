<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 19-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

use App\Actions\Dropshipping\CustomerSalesChannel\UI\IndexCustomerSalesChannels;
use App\Actions\Dropshipping\ShopifyUser\DeleteRetinaShopifyUser;
use App\Actions\Dropshipping\ShopifyUser\StoreShopifyUser;
use App\Actions\Dropshipping\Tiktok\User\AuthenticateTiktokAccount;
use App\Actions\Dropshipping\WooCommerce\AuthorizeRetinaWooCommerceUser;
use App\Actions\Dropshipping\WooCommerce\CallbackRetinaWooCommerceUser;
use App\Actions\Dropshipping\WooCommerce\Clients\FetchRetinaCustomerClientFromWooCommerce;
use App\Actions\Retina\Accounting\MitSavedCard\UI\CreateMitSavedCard;
use App\Actions\Retina\Accounting\MitSavedCard\UI\ShowRetinaMitSavedCardsDashboard;
use App\Actions\Retina\Dropshipping\ApiToken\UI\GetApiToken;
use App\Actions\Retina\Dropshipping\ApiToken\UI\ShowApiTokenRetinaDropshipping;
use App\Actions\Retina\Dropshipping\ApiToken\UI\ShowRetinaApiDropshippingDashboard;
use App\Actions\Retina\Dropshipping\Basket\UI\IndexRetinaBaskets;
use App\Actions\Retina\Dropshipping\Checkout\UI\ShowRetinaDropshippingCheckout;
use App\Actions\Retina\Dropshipping\Client\FetchRetinaCustomerClientFromShopify;
use App\Actions\Retina\Dropshipping\Client\UI\CreateRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\Client\UI\EditRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\Client\UI\IndexRetinaCustomerClients;
use App\Actions\Retina\Dropshipping\Client\UI\IndexRetinaPlatformCustomerClients;
use App\Actions\Retina\Dropshipping\Client\UI\ShowRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI\IndexDropshippingCustomerSalesChannels;
use App\Actions\Retina\Dropshipping\Orders\IndexRetinaDropshippingOrders;
use App\Actions\Retina\Dropshipping\Orders\IndexRetinaDropshippingOrdersInPlatform;
use App\Actions\Retina\Dropshipping\Orders\ShowRetinaDropshippingBasket;
use App\Actions\Retina\Dropshipping\Orders\ShowRetinaDropshippingOrder;
use App\Actions\Retina\Dropshipping\Portfolio\IndexRetinaPortfolios;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaProductsInDropshipping;
use App\Actions\Retina\Dropshipping\ShowRetinaDropshipping;
use App\Actions\Retina\Dropshipping\ShowRetinaProduct;
use App\Actions\Retina\Fulfilment\CustomerSalesChannel\UI\IndexFulfilmentCustomerSalesChannels;
use App\Actions\Retina\Platform\ShowRetinaPlatformDashboard;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->as('platform.')->group(function () {
    Route::get('/', IndexDropshippingCustomerSalesChannels::class)->name('index');
    Route::get('/dashboard', ShowRetinaDropshipping::class)->name('dashboard');

    Route::post('shopify-user', StoreShopifyUser::class)->name('shopify_user.store');
    Route::delete('shopify-user', DeleteRetinaShopifyUser::class)->name('shopify_user.delete');

    Route::post('wc-user/authorize', AuthorizeRetinaWooCommerceUser::class)->name('wc.authorize');
    Route::get('wc-user-callback', CallbackRetinaWooCommerceUser::class)->name('wc.callback');
    Route::delete('wc-user', DeleteRetinaShopifyUser::class)->name('wc.delete');
});

Route::prefix('client')->as('client.')->group(function () {
    Route::get('/', IndexRetinaCustomerClients::class)->name('index');
    Route::get('create', CreateRetinaCustomerClient::class)->name('create');
    Route::get('fetch', FetchRetinaCustomerClientFromShopify::class)->name('fetch');
    Route::get('{customerClient:reference}/show', ShowRetinaCustomerClient::class)->name('show');
    Route::get('{customerClient}/edit', EditRetinaCustomerClient::class)->name('edit');
});

Route::prefix('portfolios')->as('portfolios.')->group(function () {
    Route::get('my-portfolio', IndexRetinaPortfolios::class)->name('index');
    Route::get('my-portfolio/{product}', ShowRetinaProduct::class)->name('show');
    Route::get('products', IndexRetinaProductsInDropshipping::class)->name('products.index');
});

Route::prefix('orders')->as('orders.')->group(function () {
    Route::get('/', IndexRetinaDropshippingOrders::class)->name('index');
    Route::get('/{order}', ShowRetinaDropshippingOrder::class)->name('show');

    Route::get('/{order}/upload-templates', ShowRetinaDropshippingOrder::class)->name('upload_templates');
});

Route::prefix('platforms/{platform}')->as('platforms.')->group(function () {

    Route::get('/dashboard', ShowRetinaPlatformDashboard::class)->name('dashboard');

    Route::prefix('basket')->as('basket.')->group(function () {
        Route::get('/', IndexRetinaBaskets::class)->name('index');
        Route::get('{order}', ShowRetinaDropshippingBasket::class)->name('show');
    });

    Route::prefix('client')->as('client.')->group(function () {
        Route::get('/', IndexRetinaPlatformCustomerClients::class)->name('index');
        Route::get('create', [CreateRetinaCustomerClient::class, 'inPlatform'])->name('create');
        Route::get('fetch', [FetchRetinaCustomerClientFromShopify::class, 'inPlatform'])->name('fetch');
        Route::get('wc-fetch', [FetchRetinaCustomerClientFromWooCommerce::class, 'inPlatform'])->name('wc-fetch');
    });

    Route::prefix('portfolios')->as('portfolios.')->group(function () {
        Route::get('my-portfolio', [IndexRetinaPortfolios::class, 'inPlatform'])->name('index');
        Route::get('products', [IndexRetinaProductsInDropshipping::class, 'inPlatform'])->name('products.index');
    });

    Route::prefix('orders')->as('orders.')->group(function () {
        Route::get('/', IndexRetinaDropshippingOrdersInPlatform::class)->name('index');
        Route::get('/{order}', [ShowRetinaDropshippingOrder::class, 'inPlatform'])->name('show');
    });

    Route::prefix('api')->as('api.')->group(function () {
        Route::get('/', ShowRetinaApiDropshippingDashboard::class)->name('dashboard');
        Route::get('/show', ShowApiTokenRetinaDropshipping::class)->name('show');
        Route::get('/token', GetApiToken::class)->name('show.token');
    });
});

Route::prefix('tiktok')->name('tiktok.')->group(function () {
    Route::get('callback', AuthenticateTiktokAccount::class)->name('callback');
});

Route::prefix('saved-credit-cards')->name('mit_saved_cards.')->group(function () {
    Route::get('', ShowRetinaMitSavedCardsDashboard::class)->name('dashboard');
    Route::get('create', CreateMitSavedCard::class)->name('create');
});

Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('{order}', ShowRetinaDropshippingCheckout::class)->name('show');
});
