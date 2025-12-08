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
