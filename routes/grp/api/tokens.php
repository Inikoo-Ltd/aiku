<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Dec 2023 15:39:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\User\UI\StoreUserApiTokenFromCredentials;
use App\Actions\SysAdmin\User\UI\StoreUserApiTokenFromQRCode;
use Illuminate\Support\Facades\Route;

Route::name('mobile-app.tokens.')->group(function () {
    Route::post('tokens/qr-code', StoreUserApiTokenFromQRCode::class)->name('qr-code.store');
    Route::post('tokens/credentials', StoreUserApiTokenFromCredentials::class)->name('credentials.store');
});
