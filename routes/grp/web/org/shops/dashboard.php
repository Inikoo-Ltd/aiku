<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Mar 2025 18:36:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


use App\Actions\Catalogue\Shop\UI\GetShopDashboardTabData;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\Review\UI\IndexShopReviews;
use Illuminate\Support\Facades\Route;

Route::get('', ShowShop::class)->name('show');
Route::get('/tab-data', GetShopDashboardTabData::class)->name('tab-data');

Route::name("comms.")->prefix('comms')
    ->group(__DIR__."/comms.php");

Route::prefix("payments")
    ->name("payments.")
    ->group(__DIR__."/payments.php");

Route::prefix("statements")
    ->name("invoices.")
    ->group(__DIR__."/invoices.php");

Route::prefix('reviews')
    ->name('reviews.')
    ->group(function () {
        Route::get('/', [IndexShopReviews::class, 'inShop'])->name('index');
    });
