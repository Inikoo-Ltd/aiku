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
use App\Actions\Catalogue\Product\UI\IndexProductsInOrganisation;
use App\Actions\Catalogue\ProductCategory\UI\IndexDepartmentsInOrganisation;
use App\Actions\CRM\Customer\UI\IndexCustomersInOverview;
use App\Actions\Ordering\Order\UI\IndexOrdersInBasketInOrganisation;
use App\Actions\Ordering\Order\UI\IndexOrdersInOrganisation;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\SysAdmin\Organisation\UI\IndexHistoryInOrganisation;

Route::get('/', ShowOrganisationOverviewHub::class)->name('hub');

Route::get('/invoices', IndexInvoicesInOrganisation::class)->name('invoices.index');

Route::get('/refunds', [IndexRefunds::class, 'inOrganisation'])->name('refunds.index');
Route::get('/orders', IndexOrdersInOrganisation::class)->name('orders.index');
Route::get('/orders-in-basket', IndexOrdersInBasketInOrganisation::class)->name('orders_in_basket.index');

Route::get('/products', IndexProductsInOrganisation::class)->name('products.index');
Route::get('/departments', IndexDepartmentsInOrganisation::class)->name('departments.index');


Route::get('/customers', [IndexCustomersInOverview::class, 'inOrganisation'])->name('customers.index');

Route::name('changelog.')->prefix('changelog')->group(function () {
    Route::get('/', IndexHistoryInOrganisation::class)->name('index');
});
