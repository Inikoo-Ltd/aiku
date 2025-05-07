<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Accounting\PaymentAccountShop\UI\IndexPaymentAccountShops;
use App\Actions\Accounting\TopUp\UI\IndexTopUps;
use App\Actions\Accounting\TopUp\UI\ShowTopUp;
use App\Actions\Accounting\UI\IndexCustomerBalances;
use App\Actions\Accounting\UI\ShowAccountingShopDashboard;
use Illuminate\Support\Facades\Route;

Route::get('accounting-dashboard', ShowAccountingShopDashboard::class)->name('accounting.dashboard');

Route::get('accounting-dashboard/accounts', [IndexPaymentAccountShops::class, 'inShop'])->name('accounting.accounts.index');
Route::get('accounting-dashboard/payments', [IndexPayments::class, 'inShop'])->name('accounting.payments.index');
Route::get('accounting-dashboard/customer-balances', [IndexCustomerBalances::class, 'inShop'])->name('accounting.customer_balances.index');
Route::get('accounting-dashboard/top-ups', IndexTopUps::class)->name('accounting.top_ups.index');
Route::get('accounting-dashboard/top-ups/{topUp}', ShowTopUp::class)->name('accounting.top_ups.show');
