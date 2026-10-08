<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

use App\Actions\Tasks\UI\IndexStaffTasks;
use App\Actions\Tasks\UI\ShowStaffTask;
use App\Actions\Tasks\UI\ShowStaffTasks;
use App\Actions\Tasks\UI\ShowStaffTasksBoard;
use App\Actions\Tasks\UI\ShowStaffTasksEtaMap;
use App\Actions\Tasks\UI\ShowStaffTasksReports;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShowStaffTasks::class, 'inShop'])->name('index');
Route::get('/all', [IndexStaffTasks::class, 'inShop'])->name('list_all');
Route::get('/review', [IndexStaffTasks::class, 'inShop'])->name('review');
Route::get('/board', [ShowStaffTasksBoard::class, 'inShop'])->name('board');
Route::get('/reports', [ShowStaffTasksReports::class, 'inShop'])->name('reports');
Route::get('/eta-map', [ShowStaffTasksEtaMap::class, 'inShop'])->name('eta_map');
Route::get('/{staffTask}', [ShowStaffTask::class, 'inShop'])->name('show');
