<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-24m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

use App\Actions\Api\Group\Fulfilment\CompleteMobileStoredItemAudit;
use App\Actions\Api\Group\Fulfilment\CountMobileStoredItemAudit;
use App\Actions\Api\Group\Fulfilment\DeleteMobilePalletReturnShipment;
use App\Actions\Api\Group\Fulfilment\DeleteMobileStoredItemAuditDelta;
use App\Actions\Api\Group\Fulfilment\DispatchMobilePalletReturn;
use App\Actions\Api\Group\Fulfilment\DownloadMobilePalletReturnShipmentLabel;
use App\Actions\Api\Group\Fulfilment\FinishMobilePalletReturnPicking;
use App\Actions\Api\Group\Fulfilment\IndexMobilePalletReturns;
use App\Actions\Api\Group\Fulfilment\IndexMobilePalletReturnShippers;
use App\Actions\Api\Group\Fulfilment\IndexMobileStoredItemAuditStoredItems;
use App\Actions\Api\Group\Fulfilment\IndexMobileStoredItems;
use App\Actions\Api\Group\Fulfilment\PickMobilePalletReturnByScan;
use App\Actions\Api\Group\Fulfilment\PickMobilePalletReturnFromOtherPallet;
use App\Actions\Api\Group\Fulfilment\PickMobilePalletReturnLine;
use App\Actions\Api\Group\Fulfilment\PrintMobilePalletReturnInserts;
use App\Actions\Api\Group\Fulfilment\PrintMobilePalletReturnShipmentLabel;
use App\Actions\Api\Group\Fulfilment\SetMobilePalletReturnLineAsNotPicked;
use App\Actions\Api\Group\Fulfilment\ShowMobilePalletReturn;
use App\Actions\Api\Group\Fulfilment\ShowMobileStoredItem;
use App\Actions\Api\Group\Fulfilment\ShowMobileStoredItemAudit;
use App\Actions\Api\Group\Fulfilment\StartMobilePalletReturnPicking;
use App\Actions\Api\Group\Fulfilment\StartMobilePalletStoredItemAudit;
use App\Actions\Api\Group\Fulfilment\StoreMobilePalletReturnShipment;
use App\Actions\Api\Group\Fulfilment\UndoMobilePalletReturnLine;
use App\Actions\Api\Group\Fulfilment\IndexMobilePalletReturnAddresses;
use App\Actions\Api\Group\Fulfilment\UpdateMobilePalletReturnLeaving;
use App\Actions\Api\Group\Fulfilment\UpdateMobilePalletReturnParcels;
use Illuminate\Support\Facades\Route;

Route::get('/pallet-returns', IndexMobilePalletReturns::class)->name('pallet_returns.index');
Route::prefix('pallet-returns/{palletReturn:id}')->name('pallet_returns.')->group(function () {
    Route::get('/', ShowMobilePalletReturn::class)->name('show');
    Route::post('/start-picking', StartMobilePalletReturnPicking::class)->name('start_picking');
    Route::post('/finish-picking', FinishMobilePalletReturnPicking::class)->name('finish_picking');
    Route::post('/dispatch', DispatchMobilePalletReturn::class)->name('dispatch');
    Route::post('/inserts/print', PrintMobilePalletReturnInserts::class)->name('inserts.print');
    Route::post('/scan', PickMobilePalletReturnByScan::class)->name('scan');
    Route::post('/lines/{palletReturnItem:id}/pick', PickMobilePalletReturnLine::class)->name('lines.pick');
    Route::post('/lines/{palletReturnItem:id}/not-picked', SetMobilePalletReturnLineAsNotPicked::class)->name('lines.not_picked');
    Route::post('/lines/{palletReturnItem:id}/undo', UndoMobilePalletReturnLine::class)->name('lines.undo');
    Route::post('/pallet-stored-items/{palletStoredItem:id}/pick', PickMobilePalletReturnFromOtherPallet::class)->name('pallet_stored_items.pick');
    Route::get('/shippers', IndexMobilePalletReturnShippers::class)->name('shippers');
    Route::post('/parcels', UpdateMobilePalletReturnParcels::class)->name('parcels');
    Route::get('/addresses', IndexMobilePalletReturnAddresses::class)->name('addresses');
    Route::post('/leaving', UpdateMobilePalletReturnLeaving::class)->name('leaving');
    Route::post('/shipments', StoreMobilePalletReturnShipment::class)->name('shipments.store');
    Route::delete('/shipments/{shipment:id}', DeleteMobilePalletReturnShipment::class)->name('shipments.delete');
    Route::post('/shipments/{shipment:id}/print-label', PrintMobilePalletReturnShipmentLabel::class)->name('shipments.print_label');
    Route::get('/shipments/{shipment:id}/label', DownloadMobilePalletReturnShipmentLabel::class)->name('shipments.label');
});

Route::get('/stored-items', IndexMobileStoredItems::class)->name('stored_items.index');
Route::get('/stored-items/{storedItem:id}', ShowMobileStoredItem::class)->name('stored_items.show');

Route::post('/pallets/{pallet:id}/audit', StartMobilePalletStoredItemAudit::class)->name('pallets.audit');
Route::prefix('stored-item-audits/{storedItemAudit:id}')->name('stored_item_audits.')->group(function () {
    Route::get('/', ShowMobileStoredItemAudit::class)->name('show');
    Route::post('/count', CountMobileStoredItemAudit::class)->name('count');
    Route::delete('/deltas/{storedItemAuditDelta:id}', DeleteMobileStoredItemAuditDelta::class)->name('deltas.delete');
    Route::get('/stored-items', IndexMobileStoredItemAuditStoredItems::class)->name('stored_items');
    Route::post('/complete', CompleteMobileStoredItemAudit::class)->name('complete');
});
