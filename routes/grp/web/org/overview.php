<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

use App\Actions\Accounting\Invoice\UI\IndexInvoicesInOrganisation;
use App\Actions\Accounting\Invoice\UI\IndexRefunds;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\SysAdmin\Organisation\UI\IndexHistoryInOrganisation;

Route::get('/', ShowOrganisationOverviewHub::class)->name('hub');

Route::get('/invoices', IndexInvoicesInOrganisation::class)->name('invoices.index');
Route::get('/refunds', [IndexRefunds::class,'inOrganisation'])->name('refunds.index');
Route::get('/orders', [IndexOrders::class,'inOrganisation'])->name('orders.index');


Route::name('changelog.')->prefix('changelog')->group(function () {
    Route::get('/', IndexHistoryInOrganisation::class)->name('index');
});
