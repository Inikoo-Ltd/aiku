<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 17 May 2025 19:39:53 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Dropshipping\ShopifyUser\DeleteRetinaShopifyUser;
use App\Actions\Dropshipping\ShopifyUser\StoreShopifyUser;
use App\Actions\Dropshipping\Tiktok\User\AuthenticateTiktokAccount;
use App\Actions\Dropshipping\WooCommerce\AuthorizeRetinaWooCommerceUser;
use App\Actions\Dropshipping\WooCommerce\StoreWooCommerceUser;
use App\Actions\Retina\Accounting\MitSavedCard\UI\CreateMitSavedCard;
use App\Actions\Retina\Accounting\MitSavedCard\UI\ShowRetinaMitSavedCardsDashboard;
use App\Actions\Retina\Dropshipping\Checkout\UI\ShowRetinaDropshippingCheckout;
use App\Actions\Retina\Dropshipping\Client\UI\ShowRetinaFulfilmentCustomerClient;
use App\Actions\Retina\Dropshipping\Portfolio\IndexRetinaFulfilmentPortfolios;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaProductsInDropshipping;
use App\Actions\Retina\Dropshipping\CreateRetinaDropshippingCustomerSalesChannel;
use App\Actions\Retina\Dropshipping\Portfolio\IndexRetinaFulfilmentPortfolios;
use App\Actions\Retina\Fulfilment\Basket\UI\IndexRetinaFulfilmentBaskets;
use App\Actions\Retina\Fulfilment\CustomerSalesChannel\UI\IndexFulfilmentCustomerSalesChannels;
use App\Actions\Retina\Fulfilment\Dropshipping\Client\FetchRetinaFulfilmentCustomerClientFromShopify;
use App\Actions\Retina\Fulfilment\Dropshipping\Client\FetchRetinaFulfilmentCustomerClientFromWooCommerce;
use App\Actions\Retina\Fulfilment\Dropshipping\Client\UI\CreateRetinaFulfilmentPlatformCustomerClient;
use App\Actions\Retina\Fulfilment\Dropshipping\Client\UI\IndexRetinaFulfilmentCustomerClientsInCustomerSalesChannel;
use App\Actions\Retina\Fulfilment\Order\UI\IndexRetinaFulfilmentOrders;
use App\Actions\Retina\Fulfilment\PalletReturn\UI\ShowRetinaStoredItemReturn;
use App\Actions\Retina\Fulfilment\StoredItems\UI\IndexRetinaStoredItems;
use App\Actions\Retina\Platform\ShowRetinaCustomerSalesChannelDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/inventory', IndexRetinaStoredItems::class)->name('inventory');



Route::prefix('sale-channels')->as('customer_sales_channels.')->group(function () {

    Route::get('/', IndexFulfilmentCustomerSalesChannels::class)->name('index');

    Route::get('/create', CreateRetinaDropshippingCustomerSalesChannel::class)->name('create');



    Route::post('shopify-user', StoreShopifyUser::class)->name('shopify_user.store');
    Route::delete('shopify-user', DeleteRetinaShopifyUser::class)->name('shopify_user.delete');

    Route::prefix('wc-user')->as('wc.')->group(function () {
        Route::post('authorize', AuthorizeRetinaWooCommerceUser::class)->name('authorize');
        Route::get('callback', [AuthorizeRetinaWooCommerceUser::class, 'handleCallback'])->name('callback');
        Route::post('/', StoreWooCommerceUser::class)->name('store');
        Route::delete('/', DeleteRetinaShopifyUser::class)->name('delete');
    });

    Route::prefix('{customerSalesChannel}')->group(function () {

        Route::get('/', ShowRetinaCustomerSalesChannelDashboard::class)->name('show');

        Route::prefix('basket')->as('basket.')->group(function () {
            Route::get('/', IndexRetinaFulfilmentBaskets::class)->name('index');
            Route::get('{palletReturn}', [ShowRetinaStoredItemReturn::class, 'inBasket'])->name('show');
        });

        Route::prefix('client')->as('client.')->group(function () {
            Route::get('/', IndexRetinaFulfilmentCustomerClientsInCustomerSalesChannel::class)->name('index');
            Route::get('{customerClient}', ShowRetinaFulfilmentCustomerClient::class)->name('show');
            Route::get('create', [CreateRetinaFulfilmentPlatformCustomerClient::class, 'inPlatform'])->name('create');
            Route::get('fetch', FetchRetinaFulfilmentCustomerClientFromShopify::class)->name('fetch');
            Route::get('wc-fetch', FetchRetinaFulfilmentCustomerClientFromWooCommerce::class)->name('wc-fetch');
        });

        Route::prefix('portfolios')->as('portfolios.')->group(function () {
            Route::get('/', IndexRetinaFulfilmentPortfolios::class)->name('index');
        });

        Route::prefix('orders')->as('orders.')->group(function () {
            Route::get('/', IndexRetinaFulfilmentOrders::class)->name('index');
            Route::get('/{palletReturn}', [ShowRetinaStoredItemReturn::class, 'inOrder'])->name('show');
        });


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
