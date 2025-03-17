<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Sept 2024 13:14:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\UI\CreateRefund;
use App\Actions\Accounting\Invoice\UI\DeleteRefund;
use App\Actions\Accounting\InvoiceTransaction\CreateFullRefundInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\RefundAllInvoiceTransactions;
use App\Actions\Accounting\InvoiceTransaction\StoreRefundInvoiceTransaction;
use Illuminate\Support\Facades\Route;

Route::post('/', CreateRefund::class)->name('refund.create');

Route::name('refund.')->prefix('refund/{refund}')->group(function () {
    Route::delete('/delete', DeleteRefund::class)->name('delete');
    Route::delete('/refund-all', RefundAllInvoiceTransactions::class)->name('refund_all');
    Route::name('refund_transaction.')->prefix('/refund-transaction/{invoiceTransaction:id}')->group(function () {
        Route::post('/', StoreRefundInvoiceTransaction::class)->name('store');
        Route::post('/full-refund', CreateFullRefundInvoiceTransaction::class)->name('full_refund');
    });
});
