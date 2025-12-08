<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Dec 2023 15:37:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\User\UI\ConnectMayaWithCredentials;
use App\Actions\SysAdmin\User\UI\ConnectMayaWithQRCode;
use App\Actions\SysAdmin\User\UI\LogoutMayaApp;
use Illuminate\Support\Facades\Route;

Route::name('maya.')->group(function () {
    Route::middleware(['auth:sanctum', 'bind_group'])->group(function () {
        Route::post('logout', LogoutMayaApp::class)->name('logout');

        Route::prefix('profile')
            ->name('profile.')
            ->group(__DIR__.'/profile.php');

        Route::prefix('org/{organisation:id}')
            ->name('org.')
            ->group(__DIR__.'/org/org.php');

        Route::prefix('action')
            ->name('action.')
            ->group(__DIR__.'/action.php');
        Route::prefix('json')
            ->name('json.')
            ->group(__DIR__.'/json.php');

    });

    Route::post('connect/qr-code', ConnectMayaWithQRCode::class)->name('connect.qr_code');
    Route::post('connect/credentials', ConnectMayaWithCredentials::class)->name('connect.credentials');

});
