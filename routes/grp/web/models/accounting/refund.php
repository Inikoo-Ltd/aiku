<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Sept 2024 13:14:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\UI\CreateRefund;
use App\Actions\Accounting\Invoice\UI\DeleteRefund;
use App\Actions\Accounting\InvoiceTransaction\StoreRefundInvoiceTransaction;
use Illuminate\Support\Facades\Route;

Route::post(
    '/invoice-transaction/{refund}/refund-transaction/{invoiceTransaction:id}',
    StoreRefundInvoiceTransaction::class
)->name('invoice_transaction.refund_transaction.store');


Route::name('refund.')->prefix('refund/invoice/{invoice:id}')->group(function () {
    Route::post('/', CreateRefund::class)->name('create');
    Route::delete('/delete', DeleteRefund::class)->name('delete');
});
