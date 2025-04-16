<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 16 Mar 2024 20:36:21 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\ExportInvoices;
use App\Actions\Accounting\Invoice\ISDocInvoice;
use App\Actions\Accounting\Invoice\OmegaInvoice;
use App\Actions\Accounting\Invoice\OmegaManyInvoice;
use App\Actions\Accounting\Invoice\PdfInvoice;
use App\Actions\Accounting\Invoice\UI\EditInvoice;
use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Accounting\Invoice\UI\IndexInvoicesDeleted;
use App\Actions\Accounting\Invoice\UI\IndexRefunds;
use App\Actions\Accounting\Invoice\UI\ShowInvoice;
use App\Actions\Accounting\Invoice\UI\ShowInvoiceDeleted;
use App\Actions\Accounting\Invoice\UI\ShowRefund;
use App\Actions\Accounting\InvoiceCategory\UI\CreateInvoiceCategory;
use App\Actions\Accounting\InvoiceCategory\UI\EditInvoiceCategory;
use App\Actions\Accounting\InvoiceCategory\UI\IndexInvoiceCategories;
use App\Actions\Accounting\InvoiceCategory\UI\ShowInvoiceCategory;
use App\Actions\Accounting\OrgPaymentServiceProvider\UI\SelectOrgPaymentServiceProviders;
use App\Actions\Accounting\OrgPaymentServiceProvider\UI\ShowOrgPaymentServiceProvider;
use App\Actions\Accounting\Payment\ExportPayments;
use App\Actions\Accounting\Payment\UI\CreatePayment;
use App\Actions\Accounting\Payment\UI\EditPayment;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Accounting\Payment\UI\ShowPayment;
use App\Actions\Accounting\PaymentAccount\ExportPaymentAccounts;
use App\Actions\Accounting\PaymentAccount\UI\CreatePaymentAccount;
use App\Actions\Accounting\PaymentAccount\UI\EditPaymentAccount;
use App\Actions\Accounting\PaymentAccount\UI\IndexPaymentAccounts;
use App\Actions\Accounting\PaymentAccount\UI\ShowPaymentAccount;
use App\Actions\Accounting\PaymentAccountShop\UI\IndexPaymentAccountShops;
use App\Actions\Accounting\UI\IndexCustomerBalances;
use App\Actions\UI\Accounting\ShowAccountingDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShowAccountingDashboard::class, 'inOrganisation'])->name('dashboard');

Route::get('/providers/{orgPaymentServiceProvider}/accounts/{paymentAccount}/payments/create', [IndexPayments::class, 'inPaymentAccountInPaymentServiceProvider'])->name('org-payment-service-providers.show.payment-accounts.show.payments.create');
Route::get('/providers/{orgPaymentServiceProvider}/payments/create', [IndexPayments::class, 'inPaymentServiceProvider'])->name('org-payment-service-providers.show.payments.create');


Route::get('/providers', SelectOrgPaymentServiceProviders::class)->name('org-payment-service-providers.index');
Route::get('/providers/{orgPaymentServiceProvider}', ShowOrgPaymentServiceProvider::class)->name('org-payment-service-providers.show');
Route::get('/providers/{orgPaymentServiceProvider}/accounts', [IndexPaymentAccounts::class, 'inOrgPaymentServiceProvider'])->name('org-payment-service-providers.show.payment-accounts.index');
Route::get('/providers/{orgPaymentServiceProvider}/accounts/{paymentAccount}', [ShowPaymentAccount::class, 'inPaymentServiceProvider'])->name('org-payment-service-providers.show.payment-accounts.show');
Route::get('/providers/{orgPaymentServiceProvider}/accounts/{paymentAccount}/edit', [EditPaymentAccount::class, 'inPaymentServiceProvider'])->name('org-payment-service-providers.show.payment-accounts.edit');
Route::get('/providers/{orgPaymentServiceProvider}/accounts/{paymentAccount}/payments', [IndexPayments::class, 'inPaymentAccountInPaymentServiceProvider'])->name('org-payment-service-providers.show.payment-accounts.show.payments.index');
Route::get('/providers/{orgPaymentServiceProvider}/accounts/{paymentAccount}/payments/{payment}', [ShowPayment::class, 'inPaymentAccountInPaymentServiceProvider'])->name('org-payment-service-providers.show.payment-accounts.show.payments.show');
Route::get('/providers/{orgPaymentServiceProvider}/accounts/{paymentAccount}/payments/{payment}/edit', [EditPayment::class, 'inPaymentAccountInPaymentServiceProvider'])->name('org-payment-service-providers.show.payment-accounts.show.payments.edit');
Route::get('/providers/{orgPaymentServiceProvider}/payments', [IndexPayments::class, 'inPaymentServiceProvider'])->name('org-payment-service-providers.show.payments.index');
Route::get('/providers/{orgPaymentServiceProvider}/payments/{payment}/edit', [EditPayment::class, 'inPaymentServiceProvider'])->name('org-payment-service-providers.show.payments.edit');
Route::get('/providers/{orgPaymentServiceProvider}/payments/{payment}', [ShowPayment::class, 'inPaymentServiceProvider'])->name('org-payment-service-providers.show.payments.show');


Route::get('/accounts/{paymentAccount}/edit', EditPaymentAccount::class)->name('payment-accounts.edit');

Route::get('/accounts/create', CreatePaymentAccount::class)->name('payment-accounts.create');
Route::get('/accounts/export', ExportPaymentAccounts::class)->name('payment-accounts.export');
Route::get('/accounts/{paymentAccount}/payments/create', [CreatePayment::class, 'inPaymentAccount'])->name('payment-accounts.show.payments.create');
Route::get('/payments/create', CreatePayment::class)->name('payments.create');
Route::get('/accounts', [IndexPaymentAccounts::class, 'inOrganisation'])->name('payment-accounts.index');
Route::get('/accounts/{paymentAccount}', [ShowPaymentAccount::class, 'inOrganisation'])->name('payment-accounts.show');
Route::get('/accounts/{paymentAccount}/shops', IndexPaymentAccountShops::class)->name('payment-accounts.show.shops.index');
Route::get('/accounts/{paymentAccount}/payments', [IndexPayments::class, 'inPaymentAccount'])->name('payment-accounts.show.payments.index');
Route::get('/accounts/{paymentAccount}/payments/{payment}', [ShowPayment::class, 'inPaymentAccount'])->name('payment-accounts.show.payments.show');
Route::get('/accounts/{paymentAccount}/payments/{payment}/edit', [EditPayment::class, 'inPaymentAccount'])->name('payment-accounts.show.payments.edit');
Route::get('/payments/export', ExportPayments::class)->name('payments.export');
Route::get('/payments', [IndexPayments::class, 'inOrganisation'])->name('payments.index');
Route::get('/payments/{payment}', [ShowPayment::class, 'inOrganisation'])->name('payments.show');
Route::get('/payments/{payment}/edit', [EditPayment::class, 'inOrganisation'])->name('payments.edit');
Route::get('/invoices/{invoice}/export', PdfInvoice::class)->name('invoices.download');
Route::get('/invoices/{invoice}/is-doc', ISDocInvoice::class)->name('invoices.show.is_doc');
Route::get('/invoices/{invoice}/omega', OmegaInvoice::class)->name('invoices.show.omega');

Route::get('/invoices/export', ExportInvoices::class)->name('invoices.export');


Route::get('/invoices', IndexInvoices::class)->name('invoices.index');
Route::get('/invoices/omega', [OmegaManyInvoice::class, 'inOrganisation'])->name('invoices.index.omega');
Route::get('/invoices/{invoice}', ShowInvoice::class)->name('invoices.show');
Route::get('/invoices/{invoice}/edit', [EditInvoice::class, 'inOrganisation'])->name('invoices.edit');

Route::get('/invoices/{invoice}/refunds', [IndexRefunds::class, 'inInvoiceInOrganisation'])->name('invoices.show.refunds.index');
Route::get('/invoices/{invoice}/refunds/{refund}', [ShowRefund::class, 'inInvoiceInOrganisation'])->name('invoices.show.refunds.show');

Route::get('/refunds', IndexRefunds::class)->name('refunds.index');

Route::get('/invoices-unpaid', [IndexInvoices::class, 'unpaid'])->name('unpaid_invoices.index');
Route::get('/invoices-paid', [IndexInvoices::class, 'paid'])->name('paid_invoices.index');

Route::get('/invoices-deleted', IndexInvoicesDeleted::class)->name('deleted_invoices.index');
Route::get('/invoices-deleted/{invoiceId}', ShowInvoiceDeleted::class)->name('deleted_invoices.show');

Route::get('/invoice-categories', IndexInvoiceCategories::class)->name('invoice-categories.index');
Route::get('/invoice-categories/create', CreateInvoiceCategory::class)->name('invoice-categories.create');
Route::get('/invoice-categories/{invoiceCategory}', ShowInvoiceCategory::class)->name('invoice-categories.show');
Route::get('/invoice-categories/{invoiceCategory}/invoices', [IndexInvoices::class, 'inInvoiceCategory'])->name('invoice-categories.show.invoices.index');

Route::get('/invoice-categories/{invoiceCategory}/edit', EditInvoiceCategory::class)->name('invoice-categories.edit');

Route::get('/customer-balances', [IndexCustomerBalances::class, 'inOrganisation'])->name('balances.index');
