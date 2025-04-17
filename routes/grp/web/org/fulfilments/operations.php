<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jan 2024 11:31:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Accounting\Invoice\UI\IndexDeletedInvoices;
use App\Actions\Accounting\Invoice\UI\IndexRefunds;
use App\Actions\Accounting\Invoice\UI\ShowFulfilmentInvoice;
use App\Actions\Accounting\Invoice\UI\ShowRefund;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Accounting\PaymentAccountShop\UI\IndexPaymentAccountShops;
use App\Actions\Accounting\UI\IndexCustomerBalances;
use App\Actions\Accounting\UI\ShowAccountingShopDashboard;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\Pallet\UI\EditPallet;
use App\Actions\Fulfilment\Pallet\UI\IndexDamagedPallets;
use App\Actions\Fulfilment\Pallet\UI\IndexLostPallets;
use App\Actions\Fulfilment\Pallet\UI\IndexPallets;
use App\Actions\Fulfilment\Pallet\UI\IndexReturnedPallets;
use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Fulfilment\PalletDelivery\UI\EditPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\UI\IndexPalletDeliveries;
use App\Actions\Fulfilment\PalletDelivery\UI\ShowPalletDelivery;
use App\Actions\Fulfilment\PalletReturn\UI\IndexPalletReturns;
use App\Actions\Fulfilment\PalletReturn\UI\ShowPalletReturn;
use App\Actions\Fulfilment\PalletReturn\UI\ShowStoredItemReturn;
use App\Actions\Fulfilment\RecurringBill\UI\IndexRecurringBills;
use App\Actions\Fulfilment\RecurringBill\UI\ShowRecurringBill;
use App\Actions\Fulfilment\StoredItemAudit\UI\CreateStoredItemAudit;
use App\Actions\Fulfilment\StoredItemAudit\UI\IndexStoredItemAudits;
use Illuminate\Support\Facades\Route;

Route::get('', ShowFulfilment::class)->name('dashboard');

Route::prefix('pallets')->as('pallets.')->group(function () {

    Route::prefix('current')->as('current.')->group(function () {
        Route::get('', IndexPallets::class)->name('index');
        Route::get('{pallet}', [ShowPallet::class, 'inFulfilment'])->name('show');
        Route::get('{pallet}/edit', [EditPallet::class, 'inFulfilment'])->name('edit');
    });

    Route::prefix('returned')->as('returned.')->group(function () {
        Route::get('', [IndexReturnedPallets::class,'inFulfilment'])->name('index');
        Route::get('{pallet}', [ShowPallet::class, 'inFulfilment'])->name('show');
    });

    Route::prefix('damaged')->as('damaged.')->group(function () {
        Route::get('', [IndexDamagedPallets::class,'inFulfilment'])->name('index');
        Route::get('{pallet}', [ShowPallet::class, 'inFulfilment'])->name('show');
    });

    Route::prefix('lost')->as('lost.')->group(function () {
        Route::get('', [IndexLostPallets::class,'inFulfilment'])->name('index');
        Route::get('{pallet}', [ShowPallet::class, 'inFulfilment'])->name('show');
    });
});

Route::get('/stored-item-audits', IndexStoredItemAudits::class)->name('stored-item-audits.index');
Route::get('/stored-item-audits/create', [CreateStoredItemAudit::class, 'inFulfilment'])->name('stored-item-audits.create');

Route::get('/pallets/{pallet}', [ShowPallet::class, 'inFulfilment'])->name('pallets.show');
Route::get('/pallets/{pallet}/edit', [EditPallet::class, 'inFulfilment'])->name('pallets.edit');

Route::get('deliveries', IndexPalletDeliveries::class)->name('pallet-deliveries.index');
Route::get('deliveries/{palletDelivery}', ShowPalletDelivery::class)->name('pallet-deliveries.show');
Route::get('deliveries/{palletDelivery}/edit', EditPalletDelivery::class)->name('pallet-deliveries.edit');

Route::get('returns', IndexPalletReturns::class)->name('pallet-returns.index');
Route::get('returns/confirmed', [IndexPalletReturns::class, 'inFulfilmentConfirmed'])->name('pallet-returns.confirmed.index');
Route::get('returns/picking', [IndexPalletReturns::class, 'inFulfilmentPicking'])->name('pallet-returns.picking.index');
Route::get('returns/picked', [IndexPalletReturns::class, 'inFulfilmentPicked'])->name('pallet-returns.picked.index');
Route::get('returns/dispatched', [IndexPalletReturns::class, 'inFulfilmentDispatched'])->name('pallet-returns.dispatched.index');
Route::get('returns/cancelled', [IndexPalletReturns::class, 'inFulfilmentCancelled'])->name('pallet-returns.cancelled.index');
Route::get('returns/new', [IndexPalletReturns::class, 'inFulfilmentNew'])->name('pallet-returns.new.index');

Route::get('returns/{palletReturn}', ShowPalletReturn::class)->name('pallet-returns.show');
Route::get('return-with-stored-items/{palletReturn}', ShowStoredItemReturn::class)->name('pallet-return-with-stored-items.show');

Route::prefix('recurring_bills')->as('recurring_bills')->group(function () {
    Route::get('', IndexRecurringBills::class)->name('.index');
    Route::get('current', [IndexRecurringBills::class, 'current'])->name('.current.index');
    Route::get('former', [IndexRecurringBills::class, 'former'])->name('.former.index');

    Route::get('{recurringBill}', ShowRecurringBill::class)->name('.show');

    Route::get('current/{recurringBill}', [ShowRecurringBill::class, 'current'])->name('.current.show');
    Route::get('former/{recurringBill}', [ShowRecurringBill::class, 'former'])->name('.former.show');


});

Route::get('accounting-dashboard', [ShowAccountingShopDashboard::class, 'inFulfilment'])->name('accounting.dashboard');

Route::get('accounting-dashboard/accounts', [IndexPaymentAccountShops::class, 'inFulfilment'])->name('accounting.accounts.index');
Route::get('accounting-dashboard/payments', [IndexPayments::class, 'inFulfilment'])->name('accounting.payments.index');
Route::get('accounting-dashboard/customer-balances', [IndexCustomerBalances::class, 'inFulfilment'])->name('accounting.customer_balances.index');


Route::prefix('invoices')->as('invoices')->group(function () {
    Route::get('', [IndexInvoices::class, 'inFulfilment'])->name('.all.index');

    Route::get('/invoices-deleted', [IndexDeletedInvoices::class, 'inFulfilment'])->name('.deleted_invoices.index');

    Route::get('/unpaid-invoices', [IndexInvoices::class, 'unpaidInFulfilment'])->name('.unpaid_invoices.index');

    Route::get('/paid-invoices', [IndexInvoices::class, 'paidInFulfilment'])->name('.paid_invoices.index');
    Route::get('/refunds', [IndexRefunds::class,'inFulfilment'])->name('.refunds.index');

    Route::get('/{invoice}', ShowFulfilmentInvoice::class)->name('.show');
    Route::get('/{invoice}/refunds', [IndexRefunds::class, 'inFulfilmentInvoice'])->name('.show.refunds.index');
    Route::get('/{invoice}/refunds/{refund}', [ShowRefund::class, 'inFulfilmentInvoice'])->name('.show.refunds.show');
});




Route::prefix("comms")
    ->name("comms.")
    ->group(__DIR__."/comms.php");
