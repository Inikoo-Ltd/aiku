<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:18:41 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\FinaliseDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\PickDeliveryNoteAsEmployee;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToInQueue;
use App\Actions\Dispatching\DeliveryNote\SetDeliveryNoteStateAsPacked;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToPacking;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToPicked;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToPickerAssigned;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToPicking;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteStateToSettled;
use App\Actions\Dispatching\Picking\AssignPackerToPicking;
use App\Actions\Dispatching\Picking\AssignPickerToPicking;
use App\Actions\Dispatching\Picking\NotPickedPicking;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Picking\UpdatePicking;
use App\Actions\Helpers\Media\AttachAttachmentToModel;
use App\Actions\Helpers\Media\DetachAttachmentFromModel;
use App\Actions\Ordering\Order\ImportTransactionInOrder;
use App\Actions\Ordering\Order\PayOrder;
use App\Actions\Ordering\Order\SendOrderToWarehouse;
use App\Actions\Ordering\Order\SwitchOrderDeliveryAddress;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Ordering\Order\UpdateOrderStateToCancelled;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Order\SendOrderBackToBasket;
use App\Actions\Ordering\Order\UpdateStateToDispatchedOrder;
use App\Actions\Ordering\Order\UpdateStateToFinalizedOrder;
use App\Actions\Ordering\Order\UpdateStateToHandlingOrder;
use App\Actions\Ordering\Order\UpdateStateToPackedOrder;
use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use Illuminate\Support\Facades\Route;

Route::name('transaction.')->prefix('transaction/{transaction:id}')->group(function () {
    Route::delete('', DeleteTransaction::class)->name('delete');
    Route::patch('', UpdateTransaction::class)->name('update');
});


Route::name('order.')->prefix('order/{order:id}')->group(function () {
    Route::patch('update', UpdateOrder::class)->name('update');
    Route::post('payment-account/{paymentAccount:id}/payment', PayOrder::class)->name('payment.store')->withoutScopedBindings();
    Route::patch('address/switch', SwitchOrderDeliveryAddress::class)->name('address.switch');

    Route::name('attachment.')->prefix('attachment')->group(function () {
        Route::post('attachment/attach', [AttachAttachmentToModel::class, 'inOrder'])->name('attach');
        Route::delete('attachment/{attachment:id}/detach', [DetachAttachmentFromModel::class, 'inOrder'])->name('detach')->withoutScopedBindings();
    });

    Route::name('transaction.')->prefix('transaction')->group(function () {
        Route::post('upload', ImportTransactionInOrder::class, )->name('upload');
        Route::post('{historicAsset:id}', StoreTransaction::class)->name('store')->withoutScopedBindings();
    });

    Route::patch('send-back-to-Basket', SendOrderBackToBasket::class)->name('send_back_to_basket');

    Route::name('state.')->prefix('state')->group(function () {
        Route::patch('creating', SendOrderBackToBasket::class)->name('creating');
        Route::patch('submitted', SubmitOrder::class)->name('submitted');
        Route::patch('cancelled', UpdateOrderStateToCancelled::class)->name('cancelled');
        Route::patch('in-warehouse', SendOrderToWarehouse::class)->name('in-warehouse');
        Route::patch('handling', UpdateStateToHandlingOrder::class)->name('handling');
        Route::patch('packed', UpdateStateToPackedOrder::class)->name('packed');
        Route::patch('finalized', UpdateStateToFinalizedOrder::class)->name('finalized');
        Route::patch('dispatched', UpdateStateToDispatchedOrder::class)->name('dispatched');
    });
});

Route::name('delivery-note.')->prefix('delivery-note/{deliveryNote:id}')->group(function () {
    Route::patch('update', UpdateDeliveryNote::class)->name('update');
    Route::patch('employee-pick', PickDeliveryNoteAsEmployee::class)->name('employee.pick');
    Route::name('state.')->prefix('state')->group(function () {
        Route::patch('in-queue/{user:id}', UpdateDeliveryNoteStateToInQueue::class)->name('in-queue')->withoutScopedBindings();
        Route::patch('picker-assigned', UpdateDeliveryNoteStateToPickerAssigned::class)->name('picker-assigned');
        Route::patch('picking', UpdateDeliveryNoteStateToPicking::class)->name('picking');
        Route::patch('picked', UpdateDeliveryNoteStateToPicked::class)->name('picked');
        Route::patch('packing', UpdateDeliveryNoteStateToPacking::class)->name('packing');
        Route::patch('packed', SetDeliveryNoteStateAsPacked::class)->name('packed');
        Route::patch('finalised', FinaliseDeliveryNote::class)->name('finalised');
        Route::patch('settled', UpdateDeliveryNoteStateToSettled::class)->name('settled');
    });
});

Route::name('delivery-note-item.')->prefix('delivery-note-item/{deliveryNoteItem:id}')->group(function () {
    Route::post('picking', StorePicking::class)->name('picking.store')->withoutScopedBindings();
    Route::post('not-picking', StoreNotPickPicking::class)->name('not-picking.store')->withoutScopedBindings();
});

Route::name('picking.')->prefix('picking/{picking:id}')->group(function () {
    Route::patch('update', UpdatePicking::class)->name('update');

    Route::name('assign.')->prefix('assign')->group(function () {
        Route::patch('picker', AssignPickerToPicking::class)->name('picker');
        Route::patch('packer', AssignPackerToPicking::class)->name('packer');
    });

});
