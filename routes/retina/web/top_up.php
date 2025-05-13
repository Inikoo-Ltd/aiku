<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:02:06 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Accounting\PaymentGateway\Paypal\Orders\CapturePaymentOrderPaypal;
use App\Actions\Retina\UI\TopUp\IndexRetinaTopUp;
use App\Actions\Retina\UI\TopUp\ShowRetinaTopUpCheckout;
use App\Actions\Retina\UI\TopUp\ShowRetinaTopUpDashboard;
use Illuminate\Support\Facades\Route;

Route::get('', IndexRetinaTopUp::class)->name('index');
Route::get('/dashboard', ShowRetinaTopUpDashboard::class)->name('dashboard');
Route::get('/checkout/{topUpPaymentApiPoint:id}', ShowRetinaTopUpCheckout::class)->name('checkout');
Route::get('paypal-payment-capture/{payment:id}', CapturePaymentOrderPaypal::class)->name('paypal.capture_payment');
Route::get('paypal-payment-cancel/{paymentAccount:id}', CapturePaymentOrderPaypal::class)->name('paypal.cancel_payment');
