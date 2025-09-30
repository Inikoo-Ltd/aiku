<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 12:47:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Payment\RefundPaymentToBalance;
use Illuminate\Support\Facades\Route;

Route::name('payment.')->prefix('payment/{payment:id}')->group(function () {
    Route::post('/refund-to-balance', RefundPaymentToBalance::class)->name('refund_to_balance');
});
