<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 12:34:30 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\Picking\PickAllItem;
use App\Actions\Dispatching\Picking\PickAllItemFromWaitingWarehouse;
use App\Actions\Dispatching\Picking\PickFromMagicPlace;
use App\Actions\Dispatching\Picking\SendBackWaitingWarehouse;
use App\Actions\Dispatching\Picking\SetAsWaitingCrm;
use App\Actions\Dispatching\Picking\SetAsWaitingWarehouse;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\Dispatching\Picking\StoreNotPickPickingFromWaitingCrm;
use App\Actions\Dispatching\Picking\StoreNotPickPickingFromWaitingWarehouse;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Picking\UndoSetAsWaitingWarehouse;
use App\Actions\Dispatching\Picking\UpsertPicking;
use App\Actions\Dispatching\Picking\UpsertPickingFromWaitingWarehouse;
use App\Actions\Ordering\WaitingCrmItem\ReplaceWaitingCrmItemProduct;
use Illuminate\Support\Facades\Route;

Route::name('delivery_note_item.')->prefix('delivery-note-item/{deliveryNoteItem:id}')->group(function () {

    Route::post('replace-product', ReplaceWaitingCrmItemProduct::class)->name('waiting_items_replace_product');


    // Route::post('packing', StorePacking::class)->name('packing.store')->withoutScopedBindings();
    Route::post('picking', StorePicking::class)->name('picking.store');
    Route::post('picking', UpsertPicking::class)->name('picking.upsert');
    Route::post('picking-all', PickAllItem::class)->name('picking_all.store')->withoutScopedBindings();
    Route::post('not-picking', StoreNotPickPicking::class)->name('not_picking.store')->withoutScopedBindings();
    Route::post('not-picking-from-waiting-warehouse', StoreNotPickPickingFromWaitingWarehouse::class)->name('not_picking_from_waiting_warehouse.store')->withoutScopedBindings();
    Route::post('not-picking-fron-waiting-crm', StoreNotPickPickingFromWaitingCrm::class)->name('not_picking_from_waiting_crm.store')->withoutScopedBindings();
    
    Route::post('set-as-waiting-warehouse', SetAsWaitingWarehouse::class)->name('set_as_waiting_warehouse')->withoutScopedBindings();
    Route::post('undo-set-as-waiting-warehouse', UndoSetAsWaitingWarehouse::class)->name('undo_set_as_waiting_warehouse')->withoutScopedBindings();

    Route::post('set-as-waiting-crm', SetAsWaitingCrm::class)->name('set_as_waiting_crm')->withoutScopedBindings();
    Route::post('send-back-to-waiting-warehouse', SendBackWaitingWarehouse::class)->name('send_back_waiting_warehouse')->withoutScopedBindings();
    Route::post('pick-from-magic-place', PickFromMagicPlace::class)->name('picking.magic_place')->withoutScopedBindings();

    Route::post('picking-from-waiting-warehouse', UpsertPickingFromWaitingWarehouse::class)->name('picking.upsert_from_waiting_warehouse');
    Route::post('picking-all-from-waiting-warehouse', PickAllItemFromWaitingWarehouse::class)->name('picking_all_from_waiting_warehouse.store')->withoutScopedBindings();
});
