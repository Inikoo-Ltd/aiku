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
use App\Actions\Tasks\UI\ShowStaffTasksCovering;
use App\Actions\Tasks\UI\ShowStaffTasksReports;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShowStaffTasks::class, 'inOrganisation'])->name('index');
Route::get('/all', [IndexStaffTasks::class, 'inOrganisation'])->name('list_all');
Route::get('/review', [IndexStaffTasks::class, 'inOrganisation'])->name('review');
Route::get('/board', [ShowStaffTasksBoard::class, 'inOrganisation'])->name('board');
Route::get('/reports', [ShowStaffTasksReports::class, 'inOrganisation'])->name('reports');
Route::get('/eta-map', [ShowStaffTasksEtaMap::class, 'inOrganisation'])->name('eta_map');
Route::get('/covering', [ShowStaffTasksCovering::class, 'inOrganisation'])->name('covering');
Route::get('/{staffTask}', [ShowStaffTask::class, 'inOrganisation'])->name('show');
