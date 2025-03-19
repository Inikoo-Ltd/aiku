<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jan 2024 15:20:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\HumanResources\ClockingMachine\UI\IndexClockingMachines;
use App\Actions\HumanResources\Workplace\UI\IndexWorkplaces;
use App\Actions\HumanResources\Workplace\UI\ShowWorkplace;
use Illuminate\Support\Facades\Route;

Route::get('workplaces', IndexWorkplaces::class)->name('workplaces.index');
Route::get('workplaces/{workplace:id}', ShowWorkplace::class)->name('workplaces.show');
Route::get('workplaces/{workplace:id}/clocking-machines', IndexClockingMachines::class)->name('workplaces.show.clocking_machines.index');
Route::get('clocking-machines', [IndexClockingMachines::class, 'inOrganisation'])->name('clocking_machines.index');
