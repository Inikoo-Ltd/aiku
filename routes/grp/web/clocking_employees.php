<?php

use Illuminate\Support\Facades\Route;
use App\Actions\HumanResources\Employee\UI\IndexClockingEmployees;

Route::get('/', IndexClockingEmployees::class)->name('index');
// Route::get('/{clockingEmployee}', ShowClockingEmployee::class)->name('show');
// Route::get('/{clockingEmployee}/edit', EditClockingEmployee::class)->name('edit');
// Route::patch('/{clockingEmployee:id}/update', UpdateClockingEmployee::class)->name('update');
