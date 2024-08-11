<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 19:13:51 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Manufacturing\Artefact\UI\CreateArtefact;
use App\Actions\Manufacturing\Artefact\UI\EditArtefact;
use App\Actions\Manufacturing\Artefact\UI\IndexArtefacts;
use App\Actions\Manufacturing\Artefact\UI\ShowArtefact;
use App\Actions\Manufacturing\ManufactureTask\UI\CreateManufactureTask;
use App\Actions\Manufacturing\ManufactureTask\UI\EditManufactureTask;
use App\Actions\Manufacturing\ManufactureTask\UI\IndexManufactureTasks;
use App\Actions\Manufacturing\ManufactureTask\UI\ShowManufactureTask;
use App\Actions\Manufacturing\Production\UI\CreateProduction;
use App\Actions\Manufacturing\Production\UI\EditProduction;
use App\Actions\Manufacturing\Production\UI\IndexProductions;
use App\Actions\Manufacturing\Production\UI\ShowProduction;
use App\Actions\Manufacturing\Production\UI\ShowProductionCrafts;
use App\Actions\Manufacturing\Production\UI\ShowProductionOperations;
use App\Actions\Manufacturing\RawMaterial\UI\CreateRawMaterial;
use App\Actions\Manufacturing\RawMaterial\UI\EditRawMaterial;
use App\Actions\Manufacturing\RawMaterial\UI\IndexRawMaterials;
use App\Actions\Manufacturing\RawMaterial\UI\ShowRawMaterial;
use App\Stubs\UIDummies\IndexDummies;
use App\Stubs\UIDummies\ShowDummy;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexProductions::class)->name('index');
Route::get('create', CreateProduction::class)->name('create');

Route::prefix('{production}')
    ->group(function () {
        Route::get('', ShowProduction::class)->name('show');
        Route::get('edit', EditProduction::class)->name('edit');

        Route::name('show')
            ->group(function () {
                Route::name('.operations.')->prefix('operations')
                    ->group(function () {
                        Route::get('', ShowProductionOperations::class)->name('dashboard');
                        Route::get('artisans', ShowDummy::class)->name('artisans.index');

                        Route::get('job-orders', IndexDummies::class)->name('job-orders.index');
                        Route::get('job-orders/{jobOrder}', ShowDummy::class)->name('job-orders.show');
                    });

                Route::name('.crafts.')->prefix('crafts')
                    ->group(function () {
                        Route::get('', ShowProductionCrafts::class)->name('dashboard');

                        Route::get('raw-materials', IndexRawMaterials::class)->name('raw_materials.index');
                        Route::get('raw-materials/create', CreateRawMaterial::class)->name('raw_materials.create');
                        Route::get('raw-materials/{rawMaterial}', ShowRawMaterial::class)->name('raw_materials.show');
                        Route::get('raw-materials/{rawMaterial}/edit', EditRawMaterial::class)->name('raw_materials.edit');

                        Route::get('artefacts', IndexArtefacts::class)->name('artefacts.index');
                        Route::get('artefacts/create', CreateArtefact::class)->name('artefacts.create');
                        Route::get('artefacts/{artefact}', ShowArtefact::class)->name('artefacts.show');
                        Route::get('artefacts/{artefact}/edit', EditArtefact::class)->name('artefacts.edit');


                        Route::get('manufacture-tasks', IndexManufactureTasks::class)->name('manufacture_tasks.index');
                        Route::get('manufacture-tasks/create', CreateManufactureTask::class)->name('manufacture_tasks.create');
                        Route::get('manufacture-tasks/{manufactureTask}', ShowManufactureTask::class)->name('manufacture_tasks.show');
                        Route::get('manufacture-tasks/{manufactureTask}/edit', EditManufactureTask::class)->name('manufacture_tasks.edit');



                    });
            });
    });
