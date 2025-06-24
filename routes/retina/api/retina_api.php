<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 15:42:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Api\Retina\Dropshipping\Client\GetClients;
use App\Actions\Api\Retina\Dropshipping\GetProfile;
use App\Actions\Api\Retina\Dropshipping\Order\GetOrder;
use App\Actions\Api\Retina\Dropshipping\Order\GetOrders;
use App\Actions\Api\Retina\Dropshipping\Order\StoreApiOrder;
use App\Actions\Api\Retina\Dropshipping\Order\SubmitApiOrder;
use App\Actions\Api\Retina\Dropshipping\Order\UpdateApiOrder;
use App\Actions\Api\Retina\Dropshipping\Portfolio\GetPortfolios;
use App\Actions\Api\Retina\Dropshipping\Transaction\DeleteApiOrderTransaction;
use App\Actions\Api\Retina\Dropshipping\Transaction\GetTransactions;
use App\Actions\Api\Retina\Dropshipping\Transaction\StoreApiOrderTransaction;
use App\Actions\Api\Retina\Dropshipping\Transaction\UpdateApiOrderTransaction;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::middleware(['auth:sanctum', 'ability:retina', 'set.treblle.authorize', 'treblle'])->group(function () {
    Route::get('/profile', GetProfile::class)->name('profile');


    Route::prefix('order')->as('order.')->group(function () {
        Route::get('', GetOrders::class)->name('index');
        Route::get('{order:id}', GetOrder::class)->name('show');
        Route::post('/store', StoreApiOrder::class)->name('store');
        Route::patch('{order:id}/update', UpdateApiOrder::class)->name('update');
        Route::patch('{order:id}/submit', SubmitApiOrder::class)->name('submit');
        Route::get('{order:id}/transactions', GetTransactions::class)->name('transaction.index');
        Route::post('/{order:id}/portfolio/{portfolio:id}/store', StoreApiOrderTransaction::class)->name('transaction.store')->withoutScopedBindings();
    });

    Route::prefix('transaction')->as('transaction.')->group(function () {
        Route::patch('{transaction:id}/update', UpdateApiOrderTransaction::class)->name('update');
        Route::delete('{transaction:id}/delete', DeleteApiOrderTransaction::class)->name('delete');
    });

    Route::prefix('portfolios')->as('portfolios.')->group(function () {
        Route::get('', GetPortfolios::class)->name('index');
    });

    Route::prefix('clients')->as('clients.')->group(function () {
        Route::get('', GetClients::class)->name('index');
    });


});
