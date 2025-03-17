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

Route::post('/{invoice:id}', CreateRefund::class)->name('refund.create');

Route::name('refund.')->prefix('refund/{refund:id}')->group(function () {
    Route::delete('/delete', DeleteRefund::class)->name('delete')->withoutScopedBindings();
    Route::post('/refund-all', RefundAllInvoiceTransactions::class)->name('refund_all')->withoutScopedBindings();
    Route::name('refund_transaction.')->prefix('/refund-transaction/{invoiceTransaction:id}')->group(function () {
        Route::post('/', StoreRefundInvoiceTransaction::class)->name('store')->withoutScopedBindings();
        Route::post('/full-refund', CreateFullRefundInvoiceTransaction::class)->name('full_refund')->withoutScopedBindings();
    });
});
