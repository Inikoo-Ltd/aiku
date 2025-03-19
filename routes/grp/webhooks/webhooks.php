<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jan 2024 12:27:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Comms\Notifications\GetSnsNotification;
use App\Actions\Dropshipping\Shopify\Fulfilment\StoreFulfilmentFromShopify;
use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CatchFulfilmentOrderFromShopify;
use App\Actions\Dropshipping\Shopify\Webhook\CustomerDataRedactWebhookShopify;
use App\Actions\Dropshipping\Shopify\Webhook\CustomerDataRequestWebhookShopify;
use App\Actions\Dropshipping\Shopify\Webhook\DeleteProductWebhooksShopify;
use App\Actions\Dropshipping\Shopify\Webhook\ShopRedactWebhookShopify;
use App\Actions\Dropshipping\ShopifyUser\DeleteRetinaShopifyUser;

Route::name('webhooks.')->group(function () {
    Route::post('sns', GetSnsNotification::class)->name('sns');
});

Route::post('shopify-user/app-uninstalled', [DeleteRetinaShopifyUser::class, 'inWebhook'])->name('webhooks.shopify.app-uninstalled');
Route::prefix('shopify-user/{shopifyUser:id}')->name('webhooks.shopify.')->group(function () {
    Route::prefix('products')->as('products.')->group(function () {
        Route::post('delete', DeleteProductWebhooksShopify::class)->name('delete');
    });

    //    Route::prefix('fulfillments')->as('fulfillments.')->group(function () {
    //        // Dont change the create to store, its default needed from shopify
    //        Route::post('create', StoreFulfilmentFromShopify::class)->name('create');
    //    });

    Route::prefix('orders')->as('orders.')->group(function () {
        // Dont change the create to store, its default needed from shopify
        Route::post('create', CatchFulfilmentOrderFromShopify::class)->name('create');
    });
});

Route::middleware('verify.shopify.webhook')->group(function () {
    Route::prefix('customers')->as('customers.')->group(function () {
        Route::post('data_request', CustomerDataRequestWebhookShopify::class)->name('data_request');
        Route::post('redact', CustomerDataRedactWebhookShopify::class)->name('redact');
    });

    Route::prefix('shop')->as('shop.')->group(function () {
        Route::post('redact', ShopRedactWebhookShopify::class)->name('redact');
    });
});
