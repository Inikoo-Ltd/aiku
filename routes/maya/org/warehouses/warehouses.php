<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Aug 2024 15:53:33 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Fulfilment\UniversalScan\IndexUniversalScan;
use App\Actions\Fulfilment\UniversalScan\ShowUniversalScan;
use App\Actions\Inventory\Location\UI\IndexLocations;
use App\Actions\Inventory\Warehouse\UI\IndexWarehouses;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexWarehouses::class)->name('index');

Route::prefix('{warehouse:id}')
    ->group(function () {
        Route::get('locations', IndexLocations::class)->name('locations.index');
        Route::name('show')
            ->group(function () {

                Route::prefix('agent-inventory')->name('.agent_inventory.')
                    ->group(__DIR__."/agent_inventory.php");

                Route::prefix('inventory')->name('.inventory.')
                    ->group(__DIR__."/inventory.php");

                Route::prefix('incoming')->name('.incoming.')
                    ->group(__DIR__."/incoming.php");

                Route::prefix('dispatching')->name('.dispatching.')
                    ->group(__DIR__."/dispatching.php");

                Route::name('.infrastructure.')
                    ->group(__DIR__."/infrastructure.php");


            });
    });


Route::prefix("{warehouse:id}")->name("warehouses.")
    ->group(function () {
        Route::get('/', ShowWarehouse::class)->name('show')->withoutScopedBindings();
        Route::get('scanners/{ulid}', ShowUniversalScan::class)->name('universal.scan.show');
        Route::get('scanners', IndexUniversalScan::class)->name('universal.scan.index');

    });
