<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jan 2025 03:58:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Retina\UI\SysAdmin\CreateRetinaWebUser;
use App\Actions\Retina\UI\SysAdmin\EditRetinaWebUser;
use App\Actions\Retina\UI\SysAdmin\IndexRetinaWebUsers;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaAccountManagement;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaDropshippingSysAdminDashboard;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaSysAdminDashboard;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaWebUser;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowRetinaSysAdminDashboard::class)->name('dashboard');
Route::get('dropshipping', ShowRetinaDropshippingSysAdminDashboard::class)->name('dropshipping.dashboard');
Route::get('/settings', ShowRetinaAccountManagement::class)->name('settings.edit');
Route::get('/users', IndexRetinaWebUsers::class)->name('web-users.index');
Route::get('/users/create', CreateRetinaWebUser::class)->name('web-users.create');
Route::get('/users/{webUser}', ShowRetinaWebUser::class)->name('web-users.show');
Route::get('/users/{webUser}/edit', EditRetinaWebUser::class)->name('web-users.edit');
