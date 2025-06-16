<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 12:34:30 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Dispatching\Picking\PickAllItem;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Picking\UpsertPicking;
use Illuminate\Support\Facades\Route;

Route::name('delivery_note_item.')->prefix('delivery-note-item/{deliveryNoteItem:id}')->group(function () {
    Route::post('packing', StorePacking::class)->name('packing.store')->withoutScopedBindings();
    Route::post('picking', StorePicking::class)->name('picking.store');
    Route::post('picking', UpsertPicking::class)->name('picking.upsert');
    Route::post('picking-all', PickAllItem::class)->name('picking_all.store')->withoutScopedBindings();
    Route::post('not-picking', StoreNotPickPicking::class)->name('not_picking.store')->withoutScopedBindings();
});
