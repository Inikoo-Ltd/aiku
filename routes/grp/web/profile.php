<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 09 Sept 2022 18:32:20 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Actions\UI\Notification\IndexNotification;
use App\Actions\UI\Profile\CanVisit;
use App\Actions\UI\Profile\EditProfile;
use App\Actions\UI\Profile\EditProfileSettings;
use App\Actions\UI\Profile\ShowProfile;
use App\Actions\UI\Profile\ShowProfileIndexHistory;
use App\Actions\UI\Profile\ShowProfileIndexKpi;
use App\Actions\UI\Profile\ShowProfileIndexTimesheets;
use App\Actions\UI\Profile\ShowProfileIndexTodo;
use App\Actions\UI\Profile\ShowProfileIndexVisitLogs;
use App\Actions\UI\Profile\ShowProfilePageHeadTabs;
use App\Actions\UI\Profile\ShowProfileShowcase;
use App\Actions\UI\Profile\UpdateProfile;
use App\Actions\UI\Profile\ShowProfileIndexPassKeys;
use App\Actions\UI\Profile\StorePasskey;
use App\Actions\UI\Profile\GeneratePassKey;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowProfile::class)->name('show');
Route::get('/edit', EditProfile::class)->name('edit');
Route::get('/settings', EditProfileSettings::class)->name('settings');

Route::post('/', UpdateProfile::class)->name('update');
Route::post('/store-passkeys', StorePasskey::class)->name('passkeys.store');
Route::get('/can-visit', CanVisit::class)->name('can_visit');


Route::get('/notifications', IndexNotification::class)->name('notifications.index');

Route::get('/page-head-tabs', ShowProfilePageHeadTabs::class)->name('page-head-tabs.show');
Route::get('/showcase', ShowProfileShowcase::class)->name('showcase.show');
Route::get('/timesheets', ShowProfileIndexTimesheets::class)->name('timesheets.index');
Route::get('/histories', ShowProfileIndexHistory::class)->name('history.index');
Route::get('/visit-logs', ShowProfileIndexVisitLogs::class)->name('visit-logs.index');
Route::get('/kpis', ShowProfileIndexKpi::class)->name('kpis.index');
Route::get('/todo', ShowProfileIndexTodo::class)->name('todo.index');
Route::get('/generate-passkey', GeneratePassKey::class)->name('generate-passkey');
