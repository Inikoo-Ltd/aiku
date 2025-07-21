<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:18:41 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\Picking\AssignPackerToPicking;
use App\Actions\Dispatching\Picking\AssignPickerToPicking;
use App\Actions\Dispatching\Picking\DeletePicking;
use App\Actions\Dispatching\Picking\UpdatePicking;
use App\Actions\Helpers\Media\AttachAttachmentToModel;
use App\Actions\Helpers\Media\DetachAttachmentFromModel;
use App\Actions\Ordering\Order\CancelOrder;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Ordering\Order\ImportTransactionInOrder;
use App\Actions\Ordering\Order\PayOrder;
use App\Actions\Ordering\Order\RollbackDispatchedOrder;
use App\Actions\Ordering\Order\SendOrderToWarehouse;
use App\Actions\Ordering\Order\SwitchOrderDeliveryAddress;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Order\SendOrderBackToBasket;
use App\Actions\Ordering\Order\UpdateOrderStateToDispatched;
use App\Actions\Ordering\Order\UpdateOrderStateToHandling;
use App\Actions\Ordering\Order\UpdateOrderStateToPacked;
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
    Route::patch('rollback-dispatch', RollbackDispatchedOrder::class)->name('rollback_dispatch');
    Route::patch('generate-invoice', GenerateInvoiceFromOrder::class)->name('generate_invoice');
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
        Route::patch('cancelled', CancelOrder::class)->name('cancelled');
        Route::patch('in-warehouse', SendOrderToWarehouse::class)->name('in-warehouse');
        Route::patch('handling', UpdateOrderStateToHandling::class)->name('handling');
        Route::patch('packed', UpdateOrderStateToPacked::class)->name('packed');
        Route::patch('dispatched', UpdateOrderStateToDispatched::class)->name('dispatched');
    });
});




Route::name('picking.')->prefix('picking/{picking:id}')->group(function () {
    Route::patch('update', UpdatePicking::class)->name('update');
    Route::delete('delete', DeletePicking::class)->name('delete');

    Route::name('assign.')->prefix('assign')->group(function () {
        Route::patch('picker', AssignPickerToPicking::class)->name('picker');
        Route::patch('packer', AssignPackerToPicking::class)->name('packer');
    });
});
