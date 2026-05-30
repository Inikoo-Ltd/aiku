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
use App\Actions\Api\Retina\Fulfilment\Order\CancelApiOrder;
use App\Actions\Api\Retina\Fulfilment\Order\GetOrder;
use App\Actions\Api\Retina\Fulfilment\Order\GetOrders;
use App\Actions\Api\Retina\Fulfilment\Order\StoreApiOrder;
use App\Actions\Api\Retina\Fulfilment\Order\SubmitApiPalletReturn;
use App\Actions\Api\Retina\Fulfilment\Order\UpdateApiOrder;
use App\Actions\Api\Retina\Fulfilment\Portfolio\DeleteApiPortfolio;
use App\Actions\Api\Retina\Fulfilment\Portfolio\GetApiFulfilmentStoredItems;
use App\Actions\Api\Retina\Fulfilment\Portfolio\ShowApiPortfolio;
use App\Actions\Api\Retina\Fulfilment\Portfolio\StoreApiPortfolio;
use App\Actions\Api\Retina\Fulfilment\Portfolio\UpdateApiPortfolio;
use App\Actions\Api\Retina\Fulfilment\SKU\GetSKUs;
use App\Actions\Api\Retina\Fulfilment\Transaction\AttachApiOrderTransaction;
use App\Actions\Api\Retina\Fulfilment\Transaction\GetTransactions;

Route::prefix('order')->as('order.')->group(function () {
    Route::get('', GetOrders::class)->name('index');
    Route::post('/client/{customerClient:id}/store', StoreApiOrder::class)->name('store')->whereNumber('customerClient');
    Route::get('{palletReturn:id}', GetOrder::class)->name('show')->whereNumber('palletReturn');
    Route::patch('{palletReturn:id}/update', UpdateApiOrder::class)->name('update')->whereNumber('palletReturn');
    Route::patch('{palletReturn:id}/submit', SubmitApiPalletReturn::class)->name('submit')->whereNumber('palletReturn');
    Route::post('{palletReturn:id}/cancel', CancelApiOrder::class)->name('cancel')->whereNumber('palletReturn');
    Route::get('{palletReturn:id}/transactions', GetTransactions::class)->name('transaction.index')->whereNumber('palletReturn');
    Route::post('/{palletReturn:id}/sku/{sku:id}/store', AttachApiOrderTransaction::class)->name('transaction.store')->withoutScopedBindings()->whereNumber(['palletReturn', 'sku']);
});

Route::prefix('portfolios')->as('portfolios.')->group(function () {
    Route::get('', GetApiFulfilmentStoredItems::class)->name('index');
    Route::post('product/{product:id}/store', StoreApiPortfolio::class)->name('store')->withoutScopedBindings()->whereNumber('product');
    Route::get('{portfolio:id}', ShowApiPortfolio::class)->name('show')->whereNumber('portfolio');
    Route::patch('{portfolio:id}/update', UpdateApiPortfolio::class)->name('update')->whereNumber('portfolio');
    Route::delete('{portfolio:id}/delete', DeleteApiPortfolio::class)->name('delete')->whereNumber('portfolio');
});

Route::prefix('clients')->as('clients.')->group(function () {
    Route::get('', GetClients::class)->name('index');
    Route::post('', StoreApiCustomerClient::class)->name('create');
    Route::get('{customerClient:id}', GetClient::class)->name('show')->whereNumber('customerClient');
    Route::patch('{customerClient:id}', UpdateApiCustomerClient::class)->name('update')->whereNumber('customerClient');
    Route::delete('{customerClient:id}', DisableApiCustomerClient::class)->name('delete')->whereNumber('customerClient');

});
Route::prefix('sku')->as('sku.')->group(function () {
    Route::get('', GetSKUs::class)->name('index');
});
