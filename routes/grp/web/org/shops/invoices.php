<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Mar 2025 19:11:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\OmegaManyInvoice;
use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Accounting\Invoice\UI\IndexDeletedInvoices;
use App\Actions\Accounting\Invoice\UI\IndexRefunds;
use App\Actions\Accounting\Invoice\UI\ShowInvoice;
use App\Actions\Accounting\Invoice\UI\ShowRefund;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexInvoices::class, 'inShop'])->name('index');
Route::get('/invoices/{invoice}', [ShowInvoice::class, 'inShop'])->name('show');
Route::get('/invoices/{invoice}/refunds', [IndexRefunds::class, 'inInvoiceInShop'])->name('show.refunds.index');
Route::get('/invoices/{invoice}/refunds/{refund}', [ShowRefund::class, 'inInvoiceInShop'])->name('show.refunds.show');

Route::get('/omega', [OmegaManyInvoice::class, 'inShop'])->name('index.omega');

Route::get('/refunds', [IndexRefunds::class,'inShop'])->name('refunds.index');
Route::get('/refunds/{refund}', [ShowRefund::class, 'inShop'])->name('refunds.show');

Route::get('/invoices-unpaid', [IndexInvoices::class, 'unpaidInShop'])->name('unpaid.index');
Route::get('/invoices-paid', [IndexInvoices::class, 'paidInShop'])->name('paid.index');
Route::get('/invoices-deleted', [IndexDeletedInvoices::class, 'inShop'])->name('deleted.index');
