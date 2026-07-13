<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jan 2025 03:58:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Retina\SysAdmin\DeleteRetinaLeaflet;
use App\Actions\Retina\SysAdmin\UpdateRetinaLeaflet;
use App\Actions\Retina\SysAdmin\UpdateRetinaPackagingPreferences;
use App\Actions\Retina\SysAdmin\UploadRetinaLeaflet;
use App\Actions\Retina\UI\SysAdmin\CreateRetinaWebUser;
use App\Actions\Retina\UI\SysAdmin\EditRetinaWebUser;
use App\Actions\Retina\UI\SysAdmin\IndexRetinaVATValidationHistory;
use App\Actions\Retina\UI\SysAdmin\IndexRetinaWebUsers;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaAccountManagement;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaPackagingPreferences;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaEmailManagement;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaSysAdminDashboard;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaFulfilmentSysAdminDashboard;
use App\Actions\Retina\UI\SysAdmin\ShowRetinaWebUser;
use Illuminate\Support\Facades\Route;

Route::get('/fulfilment', ShowRetinaFulfilmentSysAdminDashboard::class)->name('fulfilment.dashboard');
Route::get('', ShowRetinaSysAdminDashboard::class)->name('dashboard');

Route::get('/vat-validation-history', IndexRetinaVATValidationHistory::class)->name('vat-validation-history');
Route::get('/settings', ShowRetinaAccountManagement::class)->name('settings.edit');
Route::get('/email', ShowRetinaEmailManagement::class)->name('email.edit');
Route::get('/packaging-preferences', ShowRetinaPackagingPreferences::class)->name('packaging-preferences.show');
Route::post('/packaging-preferences', UpdateRetinaPackagingPreferences::class)->name('packaging-preferences.update');
Route::post('/packaging-preferences/leaflet-upload', UploadRetinaLeaflet::class)->name('packaging-preferences.leaflet.upload');
Route::post('/packaging-preferences/leaflet-update', UpdateRetinaLeaflet::class)->name('packaging-preferences.leaflet.update');
Route::post('/packaging-preferences/leaflet-delete', DeleteRetinaLeaflet::class)->name('packaging-preferences.leaflet.delete');
Route::get('/users', IndexRetinaWebUsers::class)->name('web-users.index');
Route::get('/users/create', CreateRetinaWebUser::class)->name('web-users.create');
Route::get('/users/{webUser}', ShowRetinaWebUser::class)->name('web-users.show');
Route::get('/users/{webUser}/edit', EditRetinaWebUser::class)->name('web-users.edit');
