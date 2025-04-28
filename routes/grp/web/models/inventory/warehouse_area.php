<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:34:21 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Inventory\Location\ImportLocation;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\WarehouseArea\DeleteWarehouseArea;
use App\Actions\Inventory\WarehouseArea\UpdateWarehouseArea;
use Illuminate\Support\Facades\Route;

Route::name('warehouse_area.')->prefix('warehouse-area/{warehouseArea:id}')->group(function () {
    Route::patch('update', UpdateWarehouseArea::class)->name('update');
    Route::post('location/upload', [ImportLocation::class, 'inWarehouseArea'])->name('location.upload');
    Route::post('location', [StoreLocation::class, 'inWarehouseArea'])->name('location.store');
    Route::delete('delete', DeleteWarehouseArea::class)->name('delete');
});
