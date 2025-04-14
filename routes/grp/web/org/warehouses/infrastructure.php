<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jan 2024 14:40:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Inventory\Location\ExportLocations;
use App\Actions\Inventory\Location\UI\CreateLocation;
use App\Actions\Inventory\Location\UI\EditLocation;
use App\Actions\Inventory\Location\UI\IndexLocations;
use App\Actions\Inventory\Location\UI\ShowLocation;
use App\Actions\Inventory\Warehouse\UI\EditWarehouse;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\Inventory\WarehouseArea\UI\CreateWarehouseArea;
use App\Actions\Inventory\WarehouseArea\UI\EditWarehouseArea;
use App\Actions\Inventory\WarehouseArea\UI\IndexWarehouseAreas;
use App\Actions\Inventory\WarehouseArea\UI\ShowWarehouseArea;

Route::get('/', ShowWarehouse::class)->name('dashboard');
Route::get('edit', EditWarehouse::class)->name('edit');

Route::scopeBindings()->prefix('areas')->name('warehouse_areas.')->group(function () {
    Route::get('', IndexWarehouseAreas::class)->name('index');
    Route::get('create', CreateWarehouseArea::class)->name('create');


    Route::scopeBindings()->prefix('{warehouseArea}')->group(function () {
        Route::get('', ShowWarehouseArea::class)->name('show');
        Route::get('edit', EditWarehouseArea::class)->name('edit');
        Route::scopeBindings()->prefix('locations')->name('show.locations.')->group(function () {
            Route::get('export', ExportLocations::class)->name('export');
            Route::get('create', CreateLocation::class)->name('create');
            Route::get('', IndexLocations::class)->name('index');

            Route::scopeBindings()->prefix('{location}')->group(function () {
                Route::get('', [ShowLocation::class, 'inWarehouseArea'])->name('show');
                Route::get('edit', [EditLocation::class, 'inWarehouseArea'])->name('edit');
            });
        });
    });
});


Route::scopeBindings()->prefix('locations')->name('locations.')->group(function () {
    Route::get('export', [ExportLocations::class, 'inWarehouse'])->name('export');
    Route::get('create', [CreateLocation::class, 'inWarehouse'])->name('create');
    Route::get('', [IndexLocations::class, 'inWarehouse'])->name('index');

    Route::scopeBindings()->prefix('{location}')->group(function () {
        Route::get('', ShowLocation::class)->name('show');
        Route::get('edit', EditLocation::class)->name('edit');

        Route::get('pallets/{pallet}', [ShowPallet::class, 'inLocation'])->name('show.pallets.show');
    });
});
