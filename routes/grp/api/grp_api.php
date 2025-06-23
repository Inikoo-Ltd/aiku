<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 15:42:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Api\Customer\IndexApiCustomers;
use App\Actions\Api\Customer\ShowApiCustomer;
use App\Actions\Api\GetApiProfile;
use App\Actions\Api\Invoice\IndexApiInvoices;
use App\Actions\Api\Order\IndexApiOrders;
use App\Actions\Api\Order\ShowApiOrder;
use App\Actions\Catalogue\Shop\Api\IndexApiShops;
use App\Actions\SysAdmin\Group\Api\ShowApiGroup;
use App\Actions\SysAdmin\Organisation\Api\IndexApiOrganisations;
use App\Actions\SysAdmin\Organisation\Api\ShowApiOrganisation;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::middleware(['auth:sanctum', 'set.treblle.authorize'])->group(function () {
    Route::get('/profile', GetApiProfile::class)->name('profile');
    Route::get('/group', ShowApiGroup::class)->name('group.show');

    Route::get('/organisations', IndexApiOrganisations::class)->name('organisations.index');
    Route::get('/organisations/{organisation:id}', ShowApiOrganisation::class)->name('organisations.show');
    Route::get('/organisations/{organisation:id}/shops', [IndexApiShops::class, 'inOrganisation'])->name('organisations.show.shops.index');

    Route::get('/shops', IndexApiShops::class)->name('shops.index');



    Route::prefix('shop')->as('shop.')->group(function () {
        Route::prefix('{shop:id}')->as('show.')->group(function () {
            Route::prefix('orders')->as('orders.')->group(function () {
                Route::get('', IndexApiOrders::class)->name('index');
                Route::get('{order:id}', ShowApiOrder::class)->name('show');
            });
            Route::prefix('customers')->as('customers.')->group(function () {
                Route::get('', IndexApiCustomers::class)->name('index');
                Route::get('{customer:id}', ShowApiCustomer::class)->name('show');

                Route::get('invoices', [IndexApiInvoices::class, 'inCustomer'])->name('invoices');
                Route::get('orders', [IndexApiOrders::class, 'inCustomer'])->name('orders');
            });
            Route::prefix('invoices')->as('invoices.')->group(function () {
                Route::get('', IndexApiInvoices::class)->name('index');
                Route::get('{invoice:id}', ShowApiCustomer::class)->name('show');
            });
        });
    });
});
