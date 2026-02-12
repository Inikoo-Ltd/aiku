<?php

use Illuminate\Support\Facades\Route;
use App\Actions\HumanResources\Employee\UI\IndexClockingEmployees;
use App\Actions\HumanResources\Employee\UI\ShowClockingEmployee;

Route::get('/', IndexClockingEmployees::class)->name('index');
Route::get('/{timesheet}/show', ShowClockingEmployee::class)->name('show');
