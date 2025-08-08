<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Feb 2024 11:24:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\OrderPaymentApiPoint\WebHooks\RedirectSuccessPaymentOrder;
use App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks\RedirectSuccessPaymentTopUp;
use App\Actions\Retina\Helpers\Language\UpdateRetinaLocale;

Route::middleware(["retina-auth:retina", 'retina-prepare-account'])->group(function () {
    Route::get('/', function () {
        return redirect('/app/dashboard');
    })->name('home');

    Route::prefix("sysadmin")
        ->name("sysadmin.")
        ->group(__DIR__."/customer_account/sysadmin.php");

    Route::prefix("fulfilment")
        ->name("fulfilment.")
        ->group(__DIR__."/fulfilment/fulfilment_app.php");


    Route::prefix("dropshipping")
        ->name("dropshipping.")
        ->group(__DIR__."/dropshipping/dropshipping_app.php");

    Route::prefix("top-up")
        ->name("top_up.")
        ->group(__DIR__."/top_up.php");

    Route::prefix("catalogue")
        ->name("catalogue.")
        ->group(__DIR__."/catalogue.php");

    Route::prefix("/")
        ->name("ecom.")
        ->group(__DIR__."/ecom.php");

    Route::prefix("models")
        ->name("models.")
        ->group(__DIR__."/models.php");

    Route::prefix("search")
        ->name("search.")
        ->group(__DIR__."/search.php");

    Route::prefix("json")
        ->name("json.")
        ->group(__DIR__."/json.php");

    Route::prefix("helpers")
        ->name("helpers.")
        ->group(__DIR__."/helpers.php");

    Route::middleware(["retina-reset-pass"])->group(function () {
        Route::prefix("dashboard")
            ->name("dashboard.")
            ->group(__DIR__."/dashboard.php");
        Route::prefix("profile")
            ->name("profile.")
            ->group(__DIR__."/customer_account/profile.php");
    });

    Route::post('redirect-success-paid-order/{order:id}', RedirectSuccessPaymentOrder::class)->name('redirect_success_paid_order');
    Route::post('redirect-success-paid-top-up/{creditTransaction:id}', RedirectSuccessPaymentTopUp::class)->name('redirect_success_paid_top_up');


});
Route::patch('/locale/{locale}', UpdateRetinaLocale::class)->name('locale.update');
require __DIR__."/retina_auth.php";
require __DIR__."/retina_webhooks.php";
