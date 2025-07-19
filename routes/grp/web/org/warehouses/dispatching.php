<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 12:33:52 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\GoodsOut\UI\IndexWarehousePalletReturns;
use App\Actions\Dispatching\GoodsOut\UI\ShowWarehousePalletReturn;
use App\Actions\Dispatching\GoodsOut\UI\ShowWarehouseStoredItemReturn;
use App\Actions\Dispatching\Shipper\UI\CreateShipper;
use App\Actions\Dispatching\Shipper\UI\EditShipper;
use App\Actions\Dispatching\Shipper\UI\IndexShippers;
use App\Actions\Dispatching\Shipper\UI\ShowShipper;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowDispatchHub::class)->name('backlog');

Route::get('/delivery-notes', IndexDeliveryNotes::class)->name('delivery-notes');
Route::get('/delivery-notes/shop/{shopType}', [IndexDeliveryNotes::class, 'inShopTypes'])->name('delivery-notes.shop');

Route::get('/delivery-notes/unassigned', [IndexDeliveryNotes::class, 'unassigned'])->name('unassigned.delivery-notes');
Route::get('/delivery-notes/unassigned/shop/{shopType}', [IndexDeliveryNotes::class, 'unassignedShopTypes'])->name('unassigned.delivery-notes.shop');

Route::get('/delivery-notes/queued', [IndexDeliveryNotes::class, 'queued'])->name('queued.delivery-notes');
Route::get('/delivery-notes/queued/shop/{shopType}', [IndexDeliveryNotes::class, 'queuedShopTypes'])->name('queued.delivery-notes.shop');

Route::get('/delivery-notes/handling', [IndexDeliveryNotes::class, 'handling'])->name('handling.delivery-notes');
Route::get('/delivery-notes/handling/shop/{shopType}', [IndexDeliveryNotes::class, 'handlingShopTypes'])->name('handling.delivery-notes.shop');

Route::get('/delivery-notes/handling-blocked', [IndexDeliveryNotes::class, 'handlingBlocked'])->name('handling-blocked.delivery-notes');
Route::get('/delivery-notes/handling-blocked/shop/{shopType}', [IndexDeliveryNotes::class, 'handlingBlockedShopTypes'])->name('handling-blocked.delivery-notes.shop');

Route::get('/delivery-notes/packed', [IndexDeliveryNotes::class, 'packed'])->name('packed.delivery-notes');
Route::get('/delivery-notes/packed/shop/{shopType}', [IndexDeliveryNotes::class, 'packedShopTypes'])->name('packed.delivery-notes.shop');

Route::get('/delivery-notes/finalised', [IndexDeliveryNotes::class, 'finalised'])->name('finalised.delivery-notes');
Route::get('/delivery-notes/finalised/shop/{shopType}', [IndexDeliveryNotes::class, 'finalisedShopTypes'])->name('finalised.delivery-notes.shop');

Route::get('/delivery-notes/dispatched', [IndexDeliveryNotes::class, 'dispatched'])->name('dispatched.delivery-notes');
Route::get('/delivery-notes/dispatched/shop/{shopType}', [IndexDeliveryNotes::class, 'dispatchedShopTypes'])->name('dispatched.delivery-notes.shop');

Route::get('/delivery-notes/{deliveryNote}', [ShowDeliveryNote::class, 'inWarehouse'])->name('delivery-notes.show');

Route::get('returns', IndexWarehousePalletReturns::class)->name('pallet-returns.index');
Route::get('returns/confirmed', [IndexWarehousePalletReturns::class, 'inWarehouseConfirmed'])->name('pallet-returns.confirmed.index');
Route::get('returns/picking', [IndexWarehousePalletReturns::class, 'inWarehousePicking'])->name('pallet-returns.picking.index');
Route::get('returns/picked', [IndexWarehousePalletReturns::class, 'inWarehousePicked'])->name('pallet-returns.picked.index');
Route::get('returns/dispatched', [IndexWarehousePalletReturns::class, 'inWarehouseDispatched'])->name('pallet-returns.dispatched.index');
Route::get('returns/cancelled', [IndexWarehousePalletReturns::class, 'inWarehouseCancelled'])->name('pallet-returns.cancelled.index');
Route::get('returns/{palletReturn}', ShowWarehousePalletReturn::class)->name('pallet-returns.show');
Route::get('return-stored-items/{palletReturn}', ShowWarehouseStoredItemReturn::class)->name('pallet-return-with-stored-items.show');

Route::get('shippers/current', [IndexShippers::class, 'inCurrent'])->name('shippers.current.index');
Route::get('shippers/inactive', [IndexShippers::class, 'inInactive'])->name('shippers.inactive.index');
Route::get('shippers/create', CreateShipper::class)->name('shippers.create');
Route::get('shippers/{shipper}', ShowShipper::class)->name('shippers.show');
Route::get('shippers/{shipper}/edit', EditShipper::class)->name('shippers.edit');
