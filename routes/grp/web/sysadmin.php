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
use App\Actions\UI\Notification\DeleteGuestsNotificationSetting;
use App\Actions\UI\Notification\DeleteNotificationType;
use App\Actions\UI\Notification\DeleteUsersNotificationSetting;
use App\Actions\UI\Notification\GetGuestsNotificationSettingOptions;
use App\Actions\UI\Notification\GetNotificationStateOptions;
use App\Actions\UI\Notification\GetUsersNotificationSettingOptions;
use App\Actions\UI\Notification\IndexGuestsNotificationSettings;
use App\Actions\UI\Notification\IndexNotificationTypes;
use App\Actions\UI\Notification\IndexUsersNotificationSettings;
use App\Actions\UI\Notification\StoreGuestsNotificationSettings;
use App\Actions\UI\Notification\StoreNotificationType;
use App\Actions\UI\Notification\StoreUsersNotificationSettings;
use App\Actions\UI\Notification\UpdateNotificationType;
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

Route::prefix('notification-settings')->as('notification-settings.')->group(function () {
    Route::get('users', IndexUsersNotificationSettings::class)->name('users');
    Route::get('users/options', GetUsersNotificationSettingOptions::class)->name('users.options');
    Route::post('users/store', StoreUsersNotificationSettings::class)->name('users.store');
    Route::delete('users/{userNotificationSetting}', DeleteUsersNotificationSetting::class)->name('users.delete');

    Route::get('guests', IndexGuestsNotificationSettings::class)->name('guests');
    Route::get('guests/options', GetGuestsNotificationSettingOptions::class)->name('guests.options');
    Route::post('guests/store', StoreGuestsNotificationSettings::class)->name('guests.store');
    Route::delete('guests/{userNotificationSetting}', DeleteGuestsNotificationSetting::class)->name('guests.delete');

    Route::get('types', IndexNotificationTypes::class)->name('types');
    Route::post('types/store', StoreNotificationType::class)->name('types.store');
    Route::put('types/{notificationType}', UpdateNotificationType::class)->name('types.update');
    Route::delete('types/{notificationType}', DeleteNotificationType::class)->name('types.delete');

    Route::get('state-options', GetNotificationStateOptions::class)->name('state-options');
});
