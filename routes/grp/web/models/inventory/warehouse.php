<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:57:14 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dispatching\Shipper\UpdateShipper;
use App\Actions\Fulfilment\Pallet\UpdatePalletLocation;
use App\Actions\Inventory\Location\ImportLocation;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\Warehouse\DeleteWarehouse;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Inventory\Warehouse\UpdateWarehouse;
use App\Actions\Inventory\WarehouseArea\ImportWarehouseArea;
use App\Actions\Inventory\WarehouseArea\StoreWarehouseArea;
use Illuminate\Support\Facades\Route;

Route::post('organisation/{organisation:id}/warehouse', StoreWarehouse::class)->name('warehouse.store');
Route::name('warehouse.')->prefix('warehouse/{warehouse:id}')->group(function () {
    Route::patch('', UpdateWarehouse::class)->name('update');
    Route::post('area', StoreWarehouseArea::class)->name('warehouse_area.store');
    Route::post('area/upload', [ImportWarehouseArea::class, 'inWarehouse'])->name('warehouse-areas.upload');
    Route::post('location/upload', [ImportLocation::class, 'inWarehouse'])->name('location.upload');
    Route::post('location', [StoreLocation::class, 'inWarehouse'])->name('location.store');
    Route::patch('location/{pallet:id}', [UpdatePalletLocation::class, 'inWarehouse'])->name('pallets.location.update');
    Route::delete('', DeleteWarehouse::class)->name('delete');
});
Route::post('organisation/{organisation:id}/shipper', StoreShipper::class)->name('shipper.store');
Route::patch('organisation/{organisation:id}/shipper/{shipper}', UpdateShipper::class)->name('shipper.update');
