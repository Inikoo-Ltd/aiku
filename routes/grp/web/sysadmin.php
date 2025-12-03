<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:40:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Analytics\UserRequest\UI\IndexUserRequestLogs;
use App\Actions\SysAdmin\Group\UI\EditGroupSettings;
use App\Actions\SysAdmin\Guest\ExportGuests;
use App\Actions\SysAdmin\Guest\UI\CreateGuest;
use App\Actions\SysAdmin\Guest\UI\EditGuest;
use App\Actions\SysAdmin\Guest\UI\IndexGuests;
use App\Actions\SysAdmin\Guest\UI\ShowGuest;
use App\Actions\SysAdmin\UI\IndexSysAdminScheduledTasks;
use App\Actions\SysAdmin\UI\ShowSysAdminAnalyticsDashboard;
use App\Actions\SysAdmin\UI\ShowSysAdminDashboard;
use App\Actions\SysAdmin\User\ExportUsers;
use App\Actions\SysAdmin\User\UI\CreateUser;
use App\Actions\SysAdmin\User\UI\EditUser;
use App\Actions\SysAdmin\User\UI\IndexUserActions;
use App\Actions\SysAdmin\User\UI\IndexUsers;
use App\Actions\SysAdmin\User\UI\ShowUser;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowSysAdminDashboard::class)->name('dashboard');
Route::get('/settings', EditGroupSettings::class)->name('settings.edit');

Route::prefix('analytics')->as('analytics.')->group(function () {
    Route::get('', ShowSysAdminAnalyticsDashboard::class)->name('dashboard');
    Route::get('requests', IndexUserRequestLogs::class)->name('request.index');
});

Route::prefix('users')->as('users.')->group(function () {
    Route::get('active', [IndexUsers::class,'inActive'])->name('index');
    Route::get('suspended', [IndexUsers::class, 'inSuspended'])->name('suspended.index');
    Route::get('all', IndexUsers::class)->name('all.index');
    Route::get('export', ExportUsers::class)->name('export');
    Route::get('create', CreateUser::class)->name('create');

    Route::prefix('{user}')->group(function () {
        Route::get('', ShowUser::class)->name('show');
        Route::get('action', IndexUserActions::class)->name('show.actions.index');
        Route::get('edit', EditUser::class)->name('edit');
    });
});

Route::prefix('guests')->as('guests.')->group(function () {
    Route::get('active', [IndexGuests::class, 'inActive'])->name('index');
    Route::get('inactive', [IndexGuests::class, 'inInactive'])->name('inactive.index');
    Route::get('all', IndexGuests::class)->name('all.index');
    Route::get('create', CreateGuest::class)->name('create');
    Route::get('export', ExportGuests::class)->name('export');

    Route::get('{guest}', ShowGuest::class)->name('show');
    Route::get('{guest}/edit', EditGuest::class)->name('edit');
});

Route::get('/scheduled-tasks', IndexSysAdminScheduledTasks::class)->name('scheduled-tasks.index');
