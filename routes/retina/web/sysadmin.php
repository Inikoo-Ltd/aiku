<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 21:59:14 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\User\UI\CreateUser;
use App\Actions\SysAdmin\User\UI\EditUser;
use App\Actions\SysAdmin\User\UI\ShowUser;
use App\Actions\UI\Retina\SysAdmin\IndexRetinaWebUsers;
use App\Actions\UI\Retina\SysAdmin\ShowSettings;
use App\Actions\UI\Retina\SysAdmin\ShowRetinaSysAdminDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowRetinaSysAdminDashboard::class)->name('dashboard');
Route::get('/settings', ShowSettings::class)->name('settings.edit');
Route::get('/users', IndexRetinaWebUsers::class)->name('web-users.index');
Route::get('/users/create', CreateUser::class)->name('web-users.create');
Route::get('/users/{user}', ShowUser::class)->name('web-users.show');
Route::get('/users/{user}/edit', EditUser::class)->name('web-users.edit');
