<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Sept 2024 13:14:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\DeleteInProcessInvoice;
use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\Accounting\Invoice\PayInvoice;
use App\Actions\Accounting\Invoice\UpdateInvoice;
use App\Actions\Accounting\InvoiceTransaction\DeleteInProcessInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\ForceDeleteRefundInProcessInvoiceTransaction;
use App\Actions\Accounting\StandaloneFulfilmentInvoice\CompleteStandaloneFulfilmentInvoice;
use App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\DeleteStandaloneFulfilmentInvoiceTransaction;
use App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\StoreStandaloneFulfilmentInvoiceTransaction;
use App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\UpdateStandaloneFulfilmentInvoiceTransaction;
use App\Actions\Comms\Email\SendInvoiceEmailToCustomer;
use Illuminate\Support\Facades\Route;

Route::name('invoice.')->prefix('invoice/{invoice:id}')->group(function () {
    Route::patch('update', UpdateInvoice::class)->name('update');
    Route::delete('delete', DeleteInvoice::class)->name('delete');
    Route::delete('in-process/delete', DeleteInProcessInvoice::class)->name('in-process.delete');
    Route::post('payment-account/{paymentAccount:id}/payment', PayInvoice::class)->name('payment.store')->withoutScopedBindings();

    Route::post('send-invoice', SendInvoiceEmailToCustomer::class)->name('send_invoice');
});

Route::name('standalone-invoice.')->prefix('standalone-invoice/{invoice:id}')->group(function () {
    Route::post('complete', CompleteStandaloneFulfilmentInvoice::class)->name('complete');
    Route::post('transaction/{historicAsset:id}', StoreStandaloneFulfilmentInvoiceTransaction::class)->name('transaction.store')->withoutScopedBindings();
});

Route::name('standalone-invoice-transaction.')->prefix('standalone-invoice-transaction/{invoiceTransaction:id}')->group(function () {
    Route::patch('update', UpdateStandaloneFulfilmentInvoiceTransaction::class)->name('update');
    Route::delete('delete', DeleteStandaloneFulfilmentInvoiceTransaction::class)->name('delete');
});

Route::name('refund_transaction.')->prefix('refund_transaction/{invoiceTransaction:id}')->group(function () {
    Route::delete('delete', DeleteInProcessInvoiceTransaction::class)->name('delete');
    Route::delete('force-delete', ForceDeleteRefundInProcessInvoiceTransaction::class)->name('force_delete');
});
