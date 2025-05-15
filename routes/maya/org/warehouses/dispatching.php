<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 12:33:52 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\GoodsOut\UI\IndexWarehousePalletReturns;
use App\Actions\Dispatching\GoodsOut\UI\IndexWarehousePalletsInReturnAtMaya;
use App\Actions\Dispatching\GoodsOut\UI\IndexWarehousePalletStoredItemsInReturn;
use App\Actions\Dispatching\GoodsOut\UI\ShowWarehousePalletReturn;
use App\Actions\Dispatching\GoodsOut\UI\ShowWarehouseStoredItemReturn;
use App\Actions\Dispatching\Picking\UI\IndexPickings;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowDispatchHub::class)->name('backlog');
Route::get('/delivery-notes', IndexDeliveryNotes::class)->name('delivery-notes');
Route::get('/delivery-notes/{deliveryNote:id}', [ShowDeliveryNote::class, 'inWarehouse'])->name('delivery-notes.show')->withoutScopedBindings();
Route::get('delivery-notes/{deliveryNote:id}/items', IndexPickings::class)->name('delivery-notes.items.index');

Route::get('handling-fulfilment-returns', [IndexWarehousePalletReturns::class, 'inWarehouseHandling'])->name('pallet_returns.handling.index');
Route::get('dispatched-fulfilment-returns', [IndexWarehousePalletReturns::class, 'inWarehouseDispatched'])->name('pallet_returns.dispatched.index');


Route::get('fulfilment-returns/{palletReturn:id}', ShowWarehousePalletReturn::class)->name('pallet-returns.show')->withoutScopedBindings();
Route::get('fulfilment-return-stored-items/{palletReturn:id}', ShowWarehouseStoredItemReturn::class)->name('pallet-return-with-stored-items.show')->withoutScopedBindings();
Route::get('fulfilment-returns/{palletReturn:id}/pallets', IndexWarehousePalletsInReturnAtMaya::class)->name('pallet-returns.pallets.index')->withoutScopedBindings();
Route::get('fulfilment-returns/{palletReturn:id}/stored-items', IndexWarehousePalletStoredItemsInReturn::class)->name('pallet-returns.stored-items.index')->withoutScopedBindings();
