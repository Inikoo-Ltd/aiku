<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Tasks\Json\GetStaffTaskOptions;
use App\Actions\Tasks\Json\GetStaffTasks;
use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Tasks\UI\ShowStaffTasks;
use App\Actions\Tasks\UI\ShowStaffTasksBoard;
use App\Actions\Tasks\UI\ShowStaffTasksReports;
use App\Actions\Tasks\UI\IndexStaffTasks;
use App\Actions\Tasks\UpdateStaffTask;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowStaffTasks::class)->name('index');
Route::get('/all', IndexStaffTasks::class)->name('list_all');
Route::get('/board', ShowStaffTasksBoard::class)->name('board');
Route::get('/reports', ShowStaffTasksReports::class)->name('reports');
Route::get('/list', GetStaffTasks::class)->name('list');
Route::get('/options', GetStaffTaskOptions::class)->name('options');
Route::post('/', StoreStaffTask::class)->name('store');
Route::patch('/{staffTask}', UpdateStaffTask::class)->name('update');
