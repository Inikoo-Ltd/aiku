<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 Dec 2023 15:00:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Upload\UI\IndexRecentUploads;
use App\Actions\HumanResources\Calendar\IndexCalendars;
use App\Actions\HumanResources\Calendar\ShowCalendar;
use App\Actions\HumanResources\Clocking\UI\CreateClocking;
use App\Actions\HumanResources\Clocking\UI\EditClocking;
use App\Actions\HumanResources\Clocking\UI\IndexClockings;
use App\Actions\HumanResources\Clocking\UI\ShowClocking;
use App\Actions\HumanResources\ClockingMachine\ExportWorkplaces;
use App\Actions\HumanResources\ClockingMachine\UI\CreateClockingMachine;
use App\Actions\HumanResources\ClockingMachine\UI\EditClockingMachine;
use App\Actions\HumanResources\ClockingMachine\UI\IndexClockingMachines;
use App\Actions\HumanResources\ClockingMachine\UI\ShowClockingMachine;
use App\Actions\HumanResources\Employee\DownloadEmployeesTemplate;
use App\Actions\HumanResources\Employee\ExportEmployees;
use App\Actions\HumanResources\Employee\ExportEmployeeTimesheets;
use App\Actions\HumanResources\Employee\GeneratePinEmployee;
use App\Actions\HumanResources\Employee\UI\CreateEmployee;
use App\Actions\HumanResources\Employee\UI\EditEmployee;
use App\Actions\HumanResources\Employee\UI\IndexEmployees;
use App\Actions\HumanResources\Employee\UI\ShowEmployee;
use App\Actions\HumanResources\JobPosition\UI\IndexJobPositions;
use App\Actions\HumanResources\JobPosition\UI\ShowJobPosition;
use App\Actions\HumanResources\Timesheet\Pdf\PdfTimesheet;
use App\Actions\HumanResources\Timesheet\Pdf\PdfTimesheets;
use App\Actions\HumanResources\Timesheet\UI\IndexTimesheets;
use App\Actions\HumanResources\Timesheet\UI\ShowTimesheet;
use App\Actions\HumanResources\Workplace\UI\CreateWorkplace;
use App\Actions\HumanResources\Workplace\UI\EditWorkplace;
use App\Actions\HumanResources\Workplace\UI\IndexWorkplaces;
use App\Actions\HumanResources\Workplace\UI\ShowWorkplace;
use App\Actions\SysAdmin\User\UI\EditUser;
use App\Actions\SysAdmin\User\UI\IndexUsers;
use App\Actions\SysAdmin\User\UI\ShowUser;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowHumanResourcesDashboard::class)->name('dashboard');

Route::prefix('employees')->as('employees.')->group(function () {
    Route::get('', IndexEmployees::class)->name('index');
    Route::get('create', CreateEmployee::class)->name('create');
    Route::get('export', ExportEmployees::class)->name('export');
    Route::get('history-uploads', [IndexRecentUploads::class, 'inEmployee'])->name('history-uploads');
    Route::get('template', DownloadEmployeesTemplate::class)->name('uploads.templates');

    Route::prefix('{employee}')->group(function () {
        Route::get('', ShowEmployee::class)->name('show');
        Route::get('edit', EditEmployee::class)->name('edit');

        Route::get('pin', GeneratePinEmployee::class)->name('generate-pin');

        Route::as('show.')->group(function () {
            Route::get('/positions', [IndexJobPositions::class,'inEmployee'])->name('positions.index');
            Route::get('/users', [IndexUsers::class,'inEmployee'])->name('users.index');
            Route::get('/users/{user:slug}', [ShowUser::class,'inEmployee'])->name('users.show');
            Route::get('/users/{user:slug}/edit', [EditUser::class,'inEmployee'])->name('users.edit');

            Route::get('/timesheets-pdf', [PdfTimesheet::class, 'inEmployee'])->name('timesheets.pdf');
            Route::get('timesheets', [IndexTimesheets::class,'inEmployee'])->name('timesheets.index');
            Route::get('timesheets/export', ExportEmployeeTimesheets::class)->name('timesheets.export');
            Route::get('timesheets/{timesheet}', [ShowTimesheet::class, 'inEmployee'])->name('timesheets.show');
        });



    });
});


Route::get('/positions', IndexJobPositions::class)->name('job_positions.index');
Route::get('/positions/{jobPosition}', ShowJobPosition::class)->name('job_positions.show');


Route::get('/calendars', IndexCalendars::class)->name('calendars.index');
Route::get('/calendars/{calendar}', ShowCalendar::class)->name('calendars.show');

Route::get('/timesheets', IndexTimesheets::class)->name('timesheets.index');
Route::get('/timesheets-export', PdfTimesheets::class)->name('timesheets.export');
Route::get('/timesheets/{timesheet}', ShowTimesheet::class)->name('timesheets.show');

Route::get('/workplaces', IndexWorkplaces::class)->name('workplaces.index');
Route::get('/workplaces/create', CreateWorkplace::class)->name('workplaces.create');
Route::get('/workplaces/export', ExportWorkplaces::class)->name('workplaces.export');
Route::get('/workplaces/{workplace}', ShowWorkplace::class)->name('workplaces.show');
Route::get('/workplaces/{workplace}/edit', EditWorkplace::class)->name('workplaces.edit');

Route::scopeBindings()->group(function () {
    Route::get('/clocking-machines/{clockingMachine}/clockings', [IndexClockings::class, 'inClockingMachine'])->name('clocking_machines.clockings.index');
    Route::get('/clocking-machines/{clockingMachine}/clockings/create', [CreateClocking::class, 'inClockingMachine'])->name('clocking_machines.show.clockings.create');
    Route::get('/clocking-machines/{clockingMachine}/clockings/{clocking}', [ShowClocking::class, 'inClockingMachine'])->name('clocking_machines.show.clockings.show');
    Route::get('/clocking-machines/{clockingMachine}/clockings/{clocking}/edit', [EditClocking::class, 'inClockingMachine'])->name('clocking_machines.show.clockings.edit');

    Route::get('/workplaces/{workplace}/clocking-machines', IndexClockingMachines::class)->name('workplaces.show.clocking_machines.index');
    Route::get('/workplaces/{workplace}/clocking-machines/create', CreateClockingMachine::class)->name('workplaces.show.clocking_machines.create');
    Route::get('/workplaces/{workplace}/clocking-machines/{clockingMachine}', [ShowClockingMachine::class, 'inWorkplace'])->name('workplaces.show.clocking_machines.show');
    Route::get('/workplaces/{workplace}/clocking-machines/{clockingMachine}/edit', [EditClockingMachine::class, 'inWorkplace'])->name('workplaces.show.clocking_machines.edit');

    Route::get('/workplaces/{workplace}/clocking-machines/{clockingMachine}/clockings', [IndexClockings::class, 'inWorkplaceInClockingMachine'])->name('workplaces.show.clocking_machines.show.clockings.index');
    Route::get('/workplaces/{workplace}/clocking-machines/{clockingMachine}/clockings/create', [CreateClocking::class, 'inWorkplaceInClockingMachine'])->name('workplaces.show.clocking_machines.show.clockings.create');
    Route::get('/workplaces/{workplace}/clocking-machines/{clockingMachine}/clockings/{clocking}/edit', [EditClocking::class, 'inWorkplaceInClockingMachine'])->name('workplaces.show.clocking_machines.show.clockings.edit');
    Route::get('/workplaces/{workplace}/clocking-machines/{clockingMachine}/clockings/{clocking}', [ShowClocking::class, 'inWorkplaceInClockingMachine'])->name('workplaces.show.clocking_machines.show.clockings.show');

    Route::get('/workplaces/{workplace}/clockings', [IndexClockings::class, 'inWorkplace'])->name('workplaces.show.clockings.index');
    Route::get('/workplaces/{workplace}/clockings/create', [CreateClocking::class, 'inWorkplace'])->name('workplaces.show.clockings.create');
    Route::get('/workplaces/{workplace}/clockings/{clocking}', [ShowClocking::class, 'inWorkplace'])->name('workplaces.show.clockings.show');
    Route::get('/workplaces/{workplace}/clockings/{clocking}/edit', [EditClocking::class, 'inWorkplace'])->name('workplaces.show.clockings.edit');
});

Route::prefix('clocking-machines')->as('clocking_machines.')->group(function () {
    Route::get('', [IndexClockingMachines::class, 'inOrganisation'])->name('index');
    Route::get('create', [CreateClockingMachine::class, 'inOrganisation'])->name('create');
    Route::get('{clockingMachine}', [ShowClockingMachine::class,'inOrganisation'])->name('show');
    Route::get('{clockingMachine}/edit', EditClockingMachine::class)->name('edit');
});



Route::get('/clocking', IndexClockings::class)->name('clockings.index');
Route::get('/clocking/create', CreateClocking::class)->name('clockings.create');
Route::get('/clocking/{clocking}', ShowClocking::class)->name('clockings.show');
Route::get('/clocking/{clocking}/edit', EditClocking::class)->name('clockings.edit');
