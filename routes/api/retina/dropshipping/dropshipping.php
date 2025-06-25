<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Jun 2025 13:51:30 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Api\Retina\Dropshipping\Client\DeleteApiCustomerClient;
use App\Actions\Api\Retina\Dropshipping\Client\GetClients;
use App\Actions\Api\Retina\Dropshipping\Client\StoreApiCustomerClient;
use App\Actions\Api\Retina\Dropshipping\Client\UpdateApiCustomerClient;
use App\Actions\Api\Retina\Dropshipping\Order\GetOrder;
use App\Actions\Api\Retina\Dropshipping\Order\GetOrders;
use App\Actions\Api\Retina\Dropshipping\Order\StoreApiOrder;
use App\Actions\Api\Retina\Dropshipping\Order\SubmitApiOrder;
use App\Actions\Api\Retina\Dropshipping\Order\UpdateApiOrder;
use App\Actions\Api\Retina\Dropshipping\Portfolio\GetPortfolios;
use App\Actions\Api\Retina\Dropshipping\Portfolio\ShowApiPortfolio;
use App\Actions\Api\Retina\Dropshipping\Portfolio\StoreApiPortfolio;
use App\Actions\Api\Retina\Dropshipping\Portfolio\UpdateApiPortfolio;
use App\Actions\Api\Retina\Dropshipping\Transaction\DeleteApiOrderTransaction;
use App\Actions\Api\Retina\Dropshipping\Transaction\GetTransactions;
use App\Actions\Api\Retina\Dropshipping\Transaction\StoreApiOrderTransaction;
use App\Actions\Api\Retina\Dropshipping\Transaction\UpdateApiOrderTransaction;
use Illuminate\Support\Facades\Route;

Route::prefix('order')->as('order.')->group(function () {
    Route::get('', GetOrders::class)->name('index');
    Route::post('/store', StoreApiOrder::class)->name('store');
    Route::get('{order:id}', GetOrder::class)->name('show');
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
    Route::post('product/{product:id}/store', StoreApiPortfolio::class)->name('store')->withoutScopedBindings();
    Route::get('{portfolio:id}', ShowApiPortfolio::class)->name('show');
    Route::patch('{portfolio:id}/update', UpdateApiPortfolio::class)->name('update');
});

Route::prefix('clients')->as('clients.')->group(function () {
    Route::get('', GetClients::class)->name('index');
    Route::post('', StoreApiCustomerClient::class)->name('create');
    Route::patch('{customerClient:id}', UpdateApiCustomerClient::class)->name('update');
    Route::delete('{customerClient:id}', DeleteApiCustomerClient::class)->name('delete');

});
