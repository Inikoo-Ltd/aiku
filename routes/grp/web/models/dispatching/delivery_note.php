<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:18:41 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\DeliveryNote\CancelDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\FinaliseDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\PickDeliveryNoteAsEmployee;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToUnassigned;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNote\SetDeliveryNoteStateAsPacked;
use App\Actions\Dispatching\DeliveryNote\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\DispatchDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\FinaliseAndDispatchDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteDeliveryAddress;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToPacking;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToPicked;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToPicking;
use App\Actions\Dispatching\Shipment\UI\CreateShipmentInDeliveryNoteInWarehouse;
use Illuminate\Support\Facades\Route;

Route::name('delivery_note.')->prefix('delivery-note/{deliveryNote:id}')->group(function () {
    Route::patch('update', UpdateDeliveryNote::class)->name('update');
    Route::patch('update-address', UpdateDeliveryNoteDeliveryAddress::class)->name('update_address');
    Route::post('shipment-from-warehouse', CreateShipmentInDeliveryNoteInWarehouse::class)->name('shipment.store');
    Route::patch('employee-pick', PickDeliveryNoteAsEmployee::class)->name('employee.pick');
    Route::name('state.')->prefix('state')->group(function () {
        Route::patch('in-queue/{user:id}', UpdateDeliveryNoteStateToInQueue::class)->name('in_queue')->withoutScopedBindings();
        Route::patch('remove-picker', UpdateDeliveryNoteStateToUnassigned::class)->name('remove-picker');
        Route::patch('handling', StartHandlingDeliveryNote::class)->name('handling');
        Route::patch('picking', UpdateDeliveryNoteStateToPicking::class)->name('picking');
        Route::patch('picked', UpdateDeliveryNoteStateToPicked::class)->name('picked');
        Route::patch('packing', UpdateDeliveryNoteStateToPacking::class)->name('packing');
        Route::patch('packed', SetDeliveryNoteStateAsPacked::class)->name('packed');
        Route::patch('finalised', FinaliseDeliveryNote::class)->name('finalised');
        Route::patch('dispatched', DispatchDeliveryNote::class)->name('dispatched');
        Route::patch('finalised-and-dispatched', FinaliseAndDispatchDeliveryNote::class)->name('finalise_and_dispatch');
        Route::patch('cancel', CancelDeliveryNote::class)->name('cancel');
    });
});
