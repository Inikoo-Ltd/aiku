<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Jun 2025 14:00:54 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


use App\Actions\Api\Group\Customer\IndexApiCustomers;
use App\Actions\Api\Group\Customer\ShowApiCustomer;
use App\Actions\Api\Group\GetApiProfile;
use App\Actions\Api\Group\Invoice\IndexApiInvoices;
use App\Actions\Api\Group\Invoice\ShowApiInvoice;
use App\Actions\Api\Group\Order\IndexApiOrders;
use App\Actions\Api\Group\Order\ShowApiOrder;
use App\Actions\Catalogue\Shop\Api\IndexApiShops;
use App\Actions\Catalogue\Shop\Api\ShowApiShop;
use App\Actions\SysAdmin\Group\Api\ShowApiGroup;
use App\Actions\SysAdmin\Organisation\Api\IndexApiOrganisations;
use App\Actions\SysAdmin\Organisation\Api\ShowApiOrganisation;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::middleware(['auth:sanctum', 'set.treblle.authorize', 'treblle'])->group(function () {
    Route::get('/profile', GetApiProfile::class)->name('profile');
    Route::get('/group', ShowApiGroup::class)->name('group.show');

    Route::get('/organisations', IndexApiOrganisations::class)->name('organisations.index');
    Route::get('/organisations/{organisation:id}', ShowApiOrganisation::class)->name('organisations.show');
    Route::get('/organisations/{organisation:id}/shops', [IndexApiShops::class, 'inOrganisation'])->name('organisations.show.shops.index');





    Route::prefix('shops')->as('shops.')->group(function () {
        Route::get('', IndexApiShops::class)->name('index');
        Route::prefix('{shop:id}')->as('show.')->group(function () {
            Route::get('', ShowApiShop::class);
            Route::prefix('orders')->as('orders.')->group(function () {
                Route::get('', IndexApiOrders::class)->name('index');
                Route::get('{order:id}', ShowApiOrder::class)->name('show');
            });
            Route::prefix('customers')->as('customers.')->group(function () {
                Route::get('', IndexApiCustomers::class)->name('index');
                Route::get('{customer:id}', ShowApiCustomer::class)->name('show');

                Route::get('{customer:id}/invoices', [IndexApiInvoices::class, 'inCustomer'])->name('invoices');
                Route::get('{customer:id}/orders', [IndexApiOrders::class, 'inCustomer'])->name('orders');
            });
            Route::prefix('invoices')->as('invoices.')->group(function () {
                Route::get('', IndexApiInvoices::class)->name('index');
                Route::get('{invoice:id}', ShowApiInvoice::class)->name('show');
            });
        });
    });
});
