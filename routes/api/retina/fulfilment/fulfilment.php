<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Jun 2025 13:52:21 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Api\Retina\Fulfilment\Client\DisableApiCustomerClient;
use App\Actions\Api\Retina\Fulfilment\Client\GetClient;
use App\Actions\Api\Retina\Fulfilment\Client\GetClients;
use App\Actions\Api\Retina\Fulfilment\Client\StoreApiCustomerClient;
use App\Actions\Api\Retina\Fulfilment\Client\UpdateApiCustomerClient;
use App\Actions\Api\Retina\Fulfilment\Order\DeleteApiOrder;
use App\Actions\Api\Retina\Fulfilment\Order\GetOrder;
use App\Actions\Api\Retina\Fulfilment\Order\GetOrders;
use App\Actions\Api\Retina\Fulfilment\Order\StoreApiOrder;
use App\Actions\Api\Retina\Fulfilment\Order\SubmitApiOrder;
use App\Actions\Api\Retina\Fulfilment\Order\UpdateApiOrder;
use App\Actions\Api\Retina\Fulfilment\Portfolio\DeleteApiPortfolio;
use App\Actions\Api\Retina\Fulfilment\Portfolio\GetPortfolios;
use App\Actions\Api\Retina\Fulfilment\Portfolio\ShowApiPortfolio;
use App\Actions\Api\Retina\Fulfilment\Portfolio\StoreApiPortfolio;
use App\Actions\Api\Retina\Fulfilment\Portfolio\UpdateApiPortfolio;
use App\Actions\Api\Retina\Fulfilment\SKU\GetSKUs;
use App\Actions\Api\Retina\Fulfilment\Transaction\DeleteApiOrderTransaction;
use App\Actions\Api\Retina\Fulfilment\Transaction\GetTransactions;
use App\Actions\Api\Retina\Fulfilment\Transaction\StoreApiOrderTransaction;
use App\Actions\Api\Retina\Fulfilment\Transaction\UpdateApiOrderTransaction;

Route::prefix('order')->as('order.')->group(function () {
    Route::get('', GetOrders::class)->name('index');
    Route::post('/client/{customerClient:id}/store', StoreApiOrder::class)->name('store');
    Route::get('{palletReturn:id}', GetOrder::class)->name('show');
    Route::patch('{palletReturn:id}/update', UpdateApiOrder::class)->name('update');
    Route::patch('{palletReturn:id}/submit', SubmitApiOrder::class)->name('submit');
    Route::delete('{palletReturn:id}/delete', DeleteApiOrder::class)->name('delete');
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
    Route::delete('{portfolio:id}/delete', DeleteApiPortfolio::class)->name('delete');
});

Route::prefix('clients')->as('clients.')->group(function () {
    Route::get('', GetClients::class)->name('index');
    Route::post('', StoreApiCustomerClient::class)->name('create');
    Route::get('{customerClient:id}', GetClient::class)->name('show');
    Route::patch('{customerClient:id}', UpdateApiCustomerClient::class)->name('update');
    Route::delete('{customerClient:id}', DisableApiCustomerClient::class)->name('delete');

});


Route::prefix('sku')->as('sku.')->group(function () {
    Route::get('', GetSKUs::class)->name('index');
});
