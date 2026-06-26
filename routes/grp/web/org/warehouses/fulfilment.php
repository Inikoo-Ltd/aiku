<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jan 2024 12:37:34 Malaysia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Fulfilment\Pallet\UI\EditPallet;
use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInWarehouse;
use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Fulfilment\PalletDelivery\UI\IndexPalletDeliveries;
use App\Actions\Fulfilment\PickingSession\StartPickFulfilmentPickingSession;
use App\Actions\Fulfilment\PickingSession\UI\IndexFulfilmentPickingSessions;
use App\Actions\Fulfilment\PickingSession\UI\ShowFulfilmentPickingSession;
use App\Actions\Inventory\GoodsIn\UI\ShowWarehousePalletDelivery;
use App\Actions\Inventory\Location\UI\IndexFulfilmentLocations;
use App\Actions\Inventory\Location\UI\ShowFulfilmentLocation;
use App\Actions\UI\Fulfilment\ShowWarehouseFulfilmentDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowWarehouseFulfilmentDashboard::class)->name('dashboard');



Route::prefix('locations')->as('locations.')->group(function () {
    Route::get('', IndexFulfilmentLocations::class)->name('index');
    Route::get('{location}', ShowFulfilmentLocation::class)->name('show');

    Route::prefix('{location}')->as('show.')->group(function () {
        Route::prefix('pallets')->as('pallets.')->group(function () {
            Route::get('', IndexPalletsInWarehouse::class)->name('index');
            Route::get('{pallet}', [ShowPallet::class, 'inLocation'])->name('show');
            Route::get('{pallet}/edit', [EditPallet::class, 'inLocation'])->name('edit');
        });
    });

});


Route::get('deliveries', [IndexPalletDeliveries::class, 'inWarehouse'])->name('pallet-deliveries.index');
Route::get('deliveries/{palletDelivery}', ShowWarehousePalletDelivery::class)->name('pallet-deliveries.show');

Route::get('picking-sessions', IndexFulfilmentPickingSessions::class)->name('picking_sessions.index');
Route::get('picking-sessions/in-process', [IndexFulfilmentPickingSessions::class, 'InProcess'])->name('picking_sessions.in_process');
Route::get('picking-sessions/picking', [IndexFulfilmentPickingSessions::class, 'Picking'])->name('picking_sessions.picking');
Route::get('picking-sessions/waiting', [IndexFulfilmentPickingSessions::class, 'Waiting'])->name('picking_sessions.waiting');
Route::get('picking-sessions/picked', [IndexFulfilmentPickingSessions::class, 'Picked'])->name('picking_sessions.picked');
Route::get('picking-sessions/packed', [IndexFulfilmentPickingSessions::class, 'Packed'])->name('picking_sessions.packed');
Route::get('picking-sessions/{pickingSession}', ShowFulfilmentPickingSession::class)->name('picking_sessions.show');
Route::patch('picking-sessions/{pickingSession}/start-picking', StartPickFulfilmentPickingSession::class)->name('picking_sessions.start_picking');
