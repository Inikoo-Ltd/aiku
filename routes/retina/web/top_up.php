<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:02:06 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Accounting\PaymentGateway\Paypal\Orders\CapturePaymentOrderPaypal;
use App\Actions\Retina\UI\Topup\CreateRetinaTopUp;
use App\Actions\Retina\UI\Topup\IndexRetinaTopUp;
use App\Actions\Retina\UI\Topup\ShowRetinaTopUpCheckout;
use App\Actions\Retina\UI\Topup\ShowRetinaTopUpDashboard;
use Illuminate\Support\Facades\Route;

Route::get('', IndexRetinaTopUp::class)->name('index');
Route::get('/dashboard', ShowRetinaTopUpDashboard::class)->name('dashboard');
Route::get('/create', CreateRetinaTopUp::class)->name('create');
Route::get('/checkout', ShowRetinaTopUpCheckout::class)->name('checkout');

Route::get('paypal-payment-capture/{payment:id}', CapturePaymentOrderPaypal::class)->name('paypal.capture_payment');
Route::get('paypal-payment-cancel/{paymentAccount:id}', CapturePaymentOrderPaypal::class)->name('paypal.cancel_payment');
