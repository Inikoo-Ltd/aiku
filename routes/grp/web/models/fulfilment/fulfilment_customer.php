<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 00:18:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Accounting\StandaloneFulfilmentInvoice\StoreStandaloneFulfilmentInvoice;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\Fulfilment\FulfilmentCustomer\UpdateFulfilmentCustomer;
use App\Actions\Fulfilment\Pallet\DeletePalletInDelivery;
use App\Actions\Fulfilment\PalletDelivery\Pdf\PdfPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Fulfilment\PalletReturn\ConfirmPalletReturn;
use App\Actions\Fulfilment\PalletReturn\DispatchPalletReturn;
use App\Actions\Fulfilment\PalletReturn\PickedPalletReturn;
use App\Actions\Fulfilment\PalletReturn\PickingPalletReturn;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitAndConfirmPalletReturn;
use App\Actions\Fulfilment\RentalAgreement\StoreRentalAgreement;
use App\Actions\Fulfilment\StoredItem\DeleteStoredItemFromReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use Illuminate\Support\Facades\Route;

Route::name('fulfilment-customer.')->prefix('fulfilment-customer/{fulfilmentCustomer:id}')->group(function () {
    Route::patch('', UpdateFulfilmentCustomer::class)->name('update')->withoutScopedBindings();

    Route::post('stored-items', StoreStoredItem::class)->name('stored-items.store');
    Route::patch('', UpdateFulfilmentCustomer::class)->name('update');
    Route::post('pallet-delivery', StorePalletDelivery::class)->name('pallet-delivery.store');
    Route::delete('pallet-delivery/{palletDelivery:id}/pallet/{pallet:id}', DeletePalletInDelivery::class)->name('pallet-delivery.pallet.delete');
    Route::get('pallet-delivery/{palletDelivery:id}/export', PdfPalletDelivery::class)->name('pallet-delivery.export');

    // pallet return
    Route::post('pallet-return', StorePalletReturn::class)->name('pallet-return.store');
    Route::post('pallet-return-stored-items', [StorePalletReturn::class, 'withStoredItems'])->name('pallet-return-stored-items.store');
    Route::post('', [StoreWebUser::class, 'inFulfilmentCustomer'])->name('web-user.store');

    Route::prefix('pallet-return/{palletReturn:id}')->name('pallet-return.')->group(function () {
        Route::prefix('stored-item/{palletReturnItem:id}')->group(function () {
            Route::delete('', DeleteStoredItemFromReturn::class)->name('stored-item.delete')->withoutScopedBindings();
        });

        Route::post('submit-and-confirm', SubmitAndConfirmPalletReturn::class)->name('submit_and_confirm');
        Route::post('delivery', PickingPalletReturn::class)->name('picking');
        Route::post('confirm', ConfirmPalletReturn::class)->name('confirm');
        Route::post('received', PickedPalletReturn::class)->name('picked');
        Route::post('dispatched', DispatchPalletReturn::class)->name('dispatched');
    });


    Route::prefix('rental-agreements')->name('rental-agreements.')->group(function () {
        Route::post('/', StoreRentalAgreement::class)->name('store');
    });

    Route::prefix('standalone-invoice')->name('standalone-invoice.')->group(function () {
        Route::post('/', StoreStandaloneFulfilmentInvoice::class)->name('store');
    });
});
