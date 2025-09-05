<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Sept 2024 13:14:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\Accounting\Invoice\PayInvoice;
use App\Actions\Accounting\Invoice\UpdateInvoice;
use App\Actions\Comms\Email\SendInvoiceToFulfilmentCustomerEmail;
use Illuminate\Support\Facades\Route;

Route::name('invoice.')->prefix('invoice/{invoice:id}')->group(function () {
    Route::patch('update', UpdateInvoice::class)->name('update');
    Route::delete('delete', DeleteInvoice::class)->name('delete');
    Route::post('payment-account/{paymentAccount:id}/payment', PayInvoice::class)->name('payment.store')->withoutScopedBindings();
    Route::post('send-invoice', SendInvoiceToFulfilmentCustomerEmail::class)->name('send_invoice');
});
