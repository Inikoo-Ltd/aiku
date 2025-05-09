<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jan 2024 12:27:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\MitSavedCard\WebHooks\CheckoutComMitSavedCardSuccess;
use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentFailure;
use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentSuccess;
use App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks\TopUpPaymentFailure;
use App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks\TopUpPaymentSuccess;
use App\Actions\Comms\Notifications\GetSnsNotification;
use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CatchFulfilmentOrderFromShopify;
use App\Actions\Dropshipping\Shopify\Webhook\CustomerDataRedactWebhookShopify;
use App\Actions\Dropshipping\Shopify\Webhook\CustomerDataRequestWebhookShopify;
use App\Actions\Dropshipping\Shopify\Webhook\DeleteProductWebhooksShopify;
use App\Actions\Dropshipping\Shopify\Webhook\ShopRedactWebhookShopify;
use App\Actions\Dropshipping\ShopifyUser\DeleteRetinaShopifyUser;
use App\Actions\Dropshipping\Tiktok\Webhooks\HandleOrderIncomingTiktok;

Route::name('webhooks.')->group(function () {
    Route::post('sns', GetSnsNotification::class)->name('sns');
    Route::name('checkout_com.')->prefix('checkout-com')->group(function () {
        Route::get('/greeting', function () {
            return 'Hello World';
        });

        Route::get('order-payment-success/{orderPaymentApiPoint:ulid}', CheckoutComOrderPaymentSuccess::class)->name('order_payment_success');
        Route::get('order-payment-failure/{orderPaymentApiPoint:ulid}', CheckoutComOrderPaymentFailure::class)->name('order_payment_failure');

        Route::get('top-up-payment-success/{{paymentAccountShop:ulid}', TopUpPaymentSuccess::class)->name('top_up_payment_success');
        Route::get('top-up-payment-failure/{paymentAccountShop:ulid}', TopUpPaymentFailure::class)->name('top_up_payment_failure');

        Route::get('mit-saved-card-success/{mitSavedCard:ulid}', CheckoutComMitSavedCardSuccess::class)->name('mit_saved_card_success');
        Route::get('mit-saved-card-failure/{mitSavedCard:ulid}', CheckoutComOrderPaymentFailure::class)->name('mit_saved_card_failure');

    });
});


Route::prefix('shopify-user/{shopifyUser:id}')->name('webhooks.shopify.')->group(function () {
    Route::prefix('products')->as('products.')->group(function () {
        Route::post('delete', DeleteProductWebhooksShopify::class)->name('delete');
    });

    Route::post('app/uninstalled', [DeleteRetinaShopifyUser::class, 'inWebhook'])->name('app-uninstalled');
    Route::prefix('orders')->as('orders.')->group(function () {
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

Route::prefix('tiktok')->as('tiktok.')->group(function () {
    Route::post('orders', HandleOrderIncomingTiktok::class)->name('orders.create');
});
