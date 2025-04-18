<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 12:37:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Retina\Accounting\Invoice\PdfRetinaInvoice;
use App\Actions\Retina\Accounting\Invoice\Transaction\ExportRetinaFulfilmentInvoiceTransactions;
use App\Actions\Retina\Billing\UI\IndexRetinaInvoices;
use App\Actions\Retina\Billing\UI\ShowRetinaBillingDashboard;
use App\Actions\Retina\Billing\UI\ShowRetinaFulfilmentInvoice;
use App\Actions\Retina\Fulfilment\RecurringBill\UI\ShowRetinaCurrentRecurringBill;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard');
Route::get('/dashboard', ShowRetinaBillingDashboard::class)->name('dashboard');
Route::get('next-bill', ShowRetinaCurrentRecurringBill::class)->name('next_recurring_bill');

Route::prefix('invoices')->as('invoices.')->group(function () {
    Route::get('/', IndexRetinaInvoices::class)->name('index');
    Route::get('{invoice}', ShowRetinaFulfilmentInvoice::class)->name('show');
    Route::get('/{invoice}/export', PdfRetinaInvoice::class)->name('download');
    Route::get('/{invoice}/invoice-transactions/export', ExportRetinaFulfilmentInvoiceTransactions::class)->name('invoice-transactions.export');

});
