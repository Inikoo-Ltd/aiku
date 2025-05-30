<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 15 Aug 2024 08:55:48 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\Dropshipping\Shopify\Product\GetProductForShopify;
use App\Actions\Dropshipping\Shopify\Product\StorePortfolioShopify;
use App\Actions\Dropshipping\Shopify\Webhook\SetupShopifyAccount;
use App\Actions\Pupil\Auth\AuthShopifyUser;
use App\Actions\Pupil\Dashboard\ShowPupilDashboard;

Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/', ShowPupilDashboard::class)->name('home');
    Route::get('shopify-user/{shopifyUser:id}/products', GetProductForShopify::class)->name('products');
    Route::post('shopify-user/{shopifyUser:id}/products', StorePortfolioShopify::class)->name('shopify_user.product.store')->withoutScopedBindings();

    Route::post('shopify-user/{shopifyUser:id}/get-started', SetupShopifyAccount::class)->name('shopify_user.get_started.store')->withoutScopedBindings();

    Route::prefix("dashboard")
        ->name("dashboard.")
        ->group(__DIR__."/dashboard.php");

    Route::prefix("dropshipping")
        ->name("dropshipping.")
        ->group(__DIR__."/dropshipping.php");

    Route::prefix("models")
        ->name("models.")
        ->group(__DIR__."/models.php");

});

Route::match(
    ['GET', 'POST'],
    '/authenticate',
    [AuthShopifyUser::class, 'authenticate']
)->name('authenticate');

Route::get(
    '/authenticate/token',
    [AuthShopifyUser::class, 'token']
)->name('authenticate.token');
