<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 09 Sept 2022 18:32:20 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Actions\UI\Notification\IndexNotification;
use App\Actions\UI\Profile\CanVisit;
use App\Actions\UI\Profile\DeleteProfileApiToken;
use App\Actions\UI\Profile\EditProfile;
use App\Actions\UI\Profile\EditProfileSettings;
use App\Actions\UI\Profile\ShowProfile;
use App\Actions\UI\Profile\ShowProfileIndexHistory;
use App\Actions\UI\Profile\ShowProfileIndexKpi;
use App\Actions\UI\Profile\ShowProfileIndexTimesheets;
use App\Actions\UI\Profile\ShowProfileIndexTodo;
use App\Actions\UI\Profile\ShowProfileIndexApiTokens;
use App\Actions\UI\Profile\ShowProfilePageHeadTabs;
use App\Actions\UI\Profile\StoreProfileApiToken;
use App\Actions\UI\Profile\ShowProfileShowcase;
use App\Actions\UI\Profile\UpdateProfile;
use App\Actions\UI\Profile\UpdateUserBookmarks;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowProfile::class)->name('show');
Route::get('/edit', EditProfile::class)->name('edit');
Route::get('/settings', EditProfileSettings::class)->name('settings');

Route::post('/', UpdateProfile::class)->name('update');
Route::patch('/bookmarks', UpdateUserBookmarks::class)->name('bookmarks.update');
Route::get('/can-visit', CanVisit::class)->name('can_visit');


Route::get('/notifications', IndexNotification::class)->name('notifications.index');

Route::get('/page-head-tabs', ShowProfilePageHeadTabs::class)->name('page-head-tabs.show');
Route::get('/showcase', ShowProfileShowcase::class)->name('showcase.show');
Route::get('/timesheets', ShowProfileIndexTimesheets::class)->name('timesheets.index');
Route::get('/histories', ShowProfileIndexHistory::class)->name('history.index');
Route::get('/kpis', ShowProfileIndexKpi::class)->name('kpis.index');
Route::get('/todo', ShowProfileIndexTodo::class)->name('todo.index');
Route::get('/api-tokens', ShowProfileIndexApiTokens::class)->name('api-tokens.index');
Route::post('/api-tokens', StoreProfileApiToken::class)->name('api-tokens.store');
Route::delete('/api-tokens/{tokenId}', DeleteProfileApiToken::class)->name('api-tokens.delete');
