<?php

use Illuminate\Support\Facades\Route;
use App\Actions\HumanResources\Employee\UI\IndexClockingEmployees;
use App\Actions\HumanResources\Employee\UI\ShowClockingEmployee;
use App\Actions\HumanResources\Leave\StoreLeave;
use App\Actions\HumanResources\Leave\UpdateLeave;
use App\Actions\HumanResources\AttendanceAdjustment\StoreAttendanceAdjustment;

Route::get('/', IndexClockingEmployees::class)->name('index');
Route::post('/leaves', StoreLeave::class)->name('leaves.store');
Route::post('/leaves/{leave}', UpdateLeave::class)->name('leaves.update');
Route::post('/adjustments', StoreAttendanceAdjustment::class)->name('adjustments.store');
Route::get('/leaves', IndexClockingEmployees::class)->name('leaves.index');
Route::get('/adjustments', IndexClockingEmployees::class)->name('adjustments.index');
Route::get('/{timesheet}/show', ShowClockingEmployee::class)->name('show');
