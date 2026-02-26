<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:18:41 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\DeliveryNote\SaveDeliveryNoteShippingFieldsAndRetryStoreShipping;
use App\Actions\Dispatching\DeliveryNote\UndispatchDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteDeliveryAddress;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\DispatchDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseAndDispatchDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\FinaliseDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\PickDeliveryNoteAsEmployee;
use App\Actions\Dispatching\DeliveryNote\UpdateState\SetAsWaitingForPickingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\SetPackedWithPickingBaysDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartHandlingWithTrolleyDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UnpackDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToHandlingBlocked;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToHandlingBlockedWithPickedBay;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicking;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToUnassigned;
use App\Actions\Dispatching\Shipment\StoreShipmentFromFaire;
use App\Actions\Dispatching\Shipment\UI\CreateShipmentInDeliveryNoteInWarehouse;
use App\Actions\Dispatching\Trolley\ChangeTrolleyDeliveryNote;
use App\Actions\Dispatching\Trolley\SyncDeliveryNoteTrolleys;
use App\Actions\Dropshipping\Tiktok\Order\ProcessTiktokOrderShipment;
use Illuminate\Support\Facades\Route;

Route::name('delivery_note.')->prefix('delivery-note/{deliveryNote:id}')->group(function () {
    Route::patch('update', UpdateDeliveryNote::class)->name('update');
    Route::patch('update-address', UpdateDeliveryNoteDeliveryAddress::class)->name('update_address');
    Route::patch('update-shipping-fields-retry-store-shipping/{shipper:id}', SaveDeliveryNoteShippingFieldsAndRetryStoreShipping::class)
        ->name('update_shipping_fields_retry_store_shipping')->withoutScopedBindings();

    Route::post('shipment-from-warehouse', CreateShipmentInDeliveryNoteInWarehouse::class)->name('shipment.store');
    Route::post('shipment-from-tiktok', ProcessTiktokOrderShipment::class)->name('shipment.store_tiktok');
    Route::post('shipment-from-faire', StoreShipmentFromFaire::class)->name('shipment.store_faire');
    Route::patch('employee-pick', PickDeliveryNoteAsEmployee::class)->name('employee.pick');
    Route::patch('trolleys', SyncDeliveryNoteTrolleys::class)->name('trolleys.sync');

    Route::name('state.')->prefix('state')->group(function () {
        Route::patch('in-queue/{user:id}', UpdateDeliveryNoteStateToInQueue::class)->name('in_queue')->withoutScopedBindings();
        Route::patch('remove-picker', UpdateDeliveryNoteStateToUnassigned::class)->name('remove-picker');
        Route::patch('handling', StartHandlingDeliveryNote::class)->name('handling');
        Route::patch('handling-with-trolley', StartHandlingWithTrolleyDeliveryNote::class)->name('handling_with_trolley');
        Route::patch('change-trolley', ChangeTrolleyDeliveryNote::class)->name('change_trolley');
        Route::patch('picking', UpdateDeliveryNoteStateToPicking::class)->name('picking');
        Route::patch('handling-blocked', UpdateDeliveryNoteStateToHandlingBlocked::class)->name('handling_blocked');
        Route::patch('handling-blocked-with-picked-bay', UpdateDeliveryNoteStateToHandlingBlockedWithPickedBay::class)->name('handling_blocked_with_picked_bay');
        Route::patch('packing', StartPackingDeliveryNote::class)->name('packing');
        Route::patch('unpacked', UnpackDeliveryNote::class)->name('unpacked');
        Route::patch('packed', UpdateDeliveryNoteStatePacked::class)->name('packed');
        Route::patch('packed-with-picked-bay', SetPackedWithPickingBaysDeliveryNote::class)->name('packed_with_picked_bay');
        Route::patch('waiting-for-picking', SetAsWaitingForPickingDeliveryNote::class)->name('waiting_for_picking');
        Route::patch('finalised', FinaliseDeliveryNote::class)->name('finalised');
        Route::patch('dispatched', DispatchDeliveryNote::class)->name('dispatched');
        Route::patch('rollback', UndispatchDeliveryNote::class)->name('rollback');
        Route::patch('finalise-and-dispatch', FinaliseAndDispatchDeliveryNote::class)->name('finalise_and_dispatch');
        Route::patch('cancel', CancelDeliveryNote::class)->name('cancel');
    });
});
