<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 07 Mar 2023 11:12:51 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Retina\Storage\RecurringBill\UI\IndexRecurringBills;
use App\Actions\Retina\Storage\RecurringBill\UI\ShowRecurringBill;
use App\Actions\UI\Retina\Billing\UI\ShowBillingDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowBillingDashboard::class)->name('dashboard');

Route::prefix('recurring')->as('recurring.')->group(function () {
    Route::get('/', IndexRecurringBills::class)->name('index');
    Route::get('{recurringBill}', ShowRecurringBill::class)->name('show');
});

Route::prefix('invoices')->as('invoices.')->group(function () {
    Route::get('/', [IndexInvoices::class, 'inRetina'])->name('index');
});
