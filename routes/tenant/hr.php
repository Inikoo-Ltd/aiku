<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:46:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */


use App\Actions\HumanResources\Calendar\IndexCalendars;
use App\Actions\HumanResources\Calendar\ShowCalendar;
use App\Actions\HumanResources\Clocking\UI\IndexClockings;
use App\Actions\HumanResources\Clocking\UI\ShowClocking;
use App\Actions\HumanResources\ClockingMachine\UI\IndexClockingMachines;
use App\Actions\HumanRecources\ClockingMachine\UI\CreateClockingMachine;
use App\Actions\HumanResources\ClockingMachine\UI\ShowClockingMachine;
use App\Actions\HumanResources\ClockingMachine\UI\EditClockingMachine;
use App\Actions\HumanResources\Employee\CreateUserFromEmployee;
use App\Actions\HumanResources\Employee\UI\CreateEmployee;
use App\Actions\HumanResources\Employee\UI\EditEmployee;
use App\Actions\HumanResources\Employee\UI\IndexEmployees;
use App\Actions\HumanResources\Employee\UI\ShowEmployee;
use App\Actions\HumanResources\JobPosition\IndexJobPositions;
use App\Actions\HumanResources\JobPosition\ShowJobPosition;
use App\Actions\HumanResources\TimeSheet\IndexTimesheets;
use App\Actions\HumanResources\TimeSheet\ShowTimeSheet;
use App\Actions\HumanResources\WorkingPlace\UI\CreateWorkingPlace;
use App\Actions\HumanResources\WorkingPlace\UI\EditWorkingPlace;
use App\Actions\HumanResources\WorkingPlace\UI\IndexWorkingPlaces;
use App\Actions\HumanResources\WorkingPlace\UI\ShowWorkingPlace;
use App\Actions\UI\HumanResources\HumanResourcesDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', HumanResourcesDashboard::class)->name('dashboard');
Route::get('/employees', IndexEmployees::class)->name('employees.index');
Route::get('/employees/create', CreateEmployee::class)->name('employees.create');

Route::get('/employees/{employee}', ShowEmployee::class)->name('employees.show');
Route::get('/employees/{employee}/edit', EditEmployee::class)->name('employees.edit');

Route::post('/employees/{employee}/user', ShowEmployee::class)->name('employees.show.user');
Route::post('/employees/{employee}/user', CreateUserFromEmployee::class)->name('employees.show.user.store');




Route::get('/positions', IndexJobPositions::class)->name('job-positions.index');
Route::get('/positions/{jobPosition}', ShowJobPosition::class)->name('job-positions.show');

Route::get('/calendars', IndexCalendars::class)->name('calendars.index');
Route::get('/calendars/{calendar}', ShowCalendar::class)->name('calendars.show');

Route::get('/time-sheets', IndexTimeSheets::class)->name('time-sheets.index');
Route::get('/time-sheets/{timeSheet}', ShowTimesheet::class)->name('time-sheets.show');

Route::get('/working-places', IndexWorkingPlaces::class)->name('working-places.index');
Route::get('/working-places/create', CreateWorkingPlace::class)->name('working-places.create');
Route::get('/working-places/{workplace}', ShowWorkingPlace::class)->name('working-places.show');
Route::get('/working-places/{workplace}/edit', EditWorkingPlace::class)->name('working-places.edit');

Route::scopeBindings()->group(function () {

    Route::get('/clocking-machines/{clockingMachine}/locations', [IndexClockings::class, 'inClockingMachine'])->name('clocking-machines.clockings.index');
    Route::get('/clocking-machines/{clockingMachine}/locations/{clocking}', [ShowClocking::class, 'inClockingMachine'])->name('clocking-machines.show.clockings.show');
    Route::get('/clocking-machines/{clockingMachine}/locations/{clocking}/edit', [EditClockingMachine::class, 'inWarehouseArea'])->name('clocking-machines.show.clockings.edit');

    Route::get('/working-places/{workplace}/clocking-machines', [IndexClockingMachines::class, 'inWorkplace'])->name('working-places.show.clocking-machines.index');
    Route::get('/working-places/{workplace}/clocking-machines/create', CreateClockingMachine::class)->name('working-places.show.clocking-machines.create');
    Route::get('/working-places/{workplace}/clocking-machines/{clockingMachine}', [ShowClockingMachine::class, 'inWorkplace'])->name('working-places.show.clocking-machines.show');
    Route::get('/working-places/{workplace}/clocking-machines/{clockingMachine}/edit', [EditClockingMachine::class, 'inWorkplace'])->name('working-places.show.clocking-machines.edit');

    Route::get('/working-places/{workplace}/clockings', [IndexClockings::class, 'inWorkplace'])->name('working-places.show.clockings.index');
    Route::get('/working-places/{workplace}/clockings/create', CreateClockingMachine::class)->name('working-places.show.clockings.create');
    Route::get('/working-places/{workplace}/clockings/{clocking}', [ShowClockingMachine::class, 'inWorkplace'])->name('working-places.show.clockings.show');
    Route::get('/working-places/{workplace}/clockings/{clocking}/edit', [EditClockingMachine::class, 'inWorkplace'])->name('working-places.show.clockings.edit');

});

Route::get('/clocking-machines', [IndexClockingMachines::class, 'inTenant'])->name('clocking-machines.index');
Route::get('/clocking-machines/create', CreateClockingMachine::class)->name('clocking-machines.create');
Route::get('/clocking-machines/{clockingMachine}', ShowClockingMachine::class, 'inTenant')->name('clocking-machines.show');
Route::get('/clocking-machines/{clockingMachine}/edit', EditClockingMachine::class)->name('clocking-machines.edit');

Route::get('/clocking', IndexClockings::class)->name('clockings.index');
Route::get('/clocking/{clocking}', ShowClocking::class)->name('clockings.show');
