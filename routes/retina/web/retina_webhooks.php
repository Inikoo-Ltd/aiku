<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 14:26:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Accounting\MitSavedCard\WebHooks\CheckoutComMitSavedCardFailure;
use App\Actions\Accounting\MitSavedCard\WebHooks\CheckoutComMitSavedCardSuccess;
use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentFailure;
use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\CheckoutComOrderPaymentSuccess;
use App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks\TopUpPaymentFailure;
use App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks\TopUpPaymentSuccess;

Route::name('webhooks.')->prefix('webhooks')->group(function () {
    Route::name('checkout_com.')->prefix('checkout-com')->group(function () {
        Route::get('/greeting', function () {
            return 'Hello World';
        });

        Route::get('order-payment-success/{orderPaymentApiPoint:ulid}', CheckoutComOrderPaymentSuccess::class)->name('order_payment_success');
        Route::get('order-payment-failure/{orderPaymentApiPoint:ulid}', CheckoutComOrderPaymentFailure::class)->name('order_payment_failure');

        Route::get('top-up-payment-success/{topUpPaymentApiPoint:ulid}', TopUpPaymentSuccess::class)->name('top_up_payment_success');
        Route::get('top-up-payment-failure/{topUpPaymentApiPoint:ulid}', TopUpPaymentFailure::class)->name('top_up_payment_failure');

        Route::get('mit-saved-card-success/{mitSavedCard:ulid}', CheckoutComMitSavedCardSuccess::class)->name('mit_saved_card_success');
        Route::get('mit-saved-card-failure/{mitSavedCard:ulid}', CheckoutComMitSavedCardFailure::class)->name('mit_saved_card_failure');

    });
});
