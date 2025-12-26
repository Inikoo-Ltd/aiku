<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 10 Aug 2022 15:52:49 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

use App\Actions\SysAdmin\UI\Auth\Login;
use App\Actions\SysAdmin\UI\Auth\Logout;
use App\Actions\SysAdmin\UI\Auth\ShowLogin;
use App\Actions\SysAdmin\UI\Auth\ShowResetPassword;
use App\Actions\SysAdmin\User\PasswordResetLink;
use App\Actions\SysAdmin\User\UI\ShowSetNewPassword;
use App\Actions\SysAdmin\User\UpdateUserPassword;
use App\Actions\SysAdmin\User\UpdateUserPasswordViaEmail;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('resetpassword', ShowResetPassword::class)->name('reset.password');
    Route::get('login', ShowLogin::class)->name('login.show');
    Route::post('login', Login::class)->name('login.store');
    // Route::get('login/passkey', function () {
    //     return response('Passkey authentication options');
    // })->name('passkeys.authentication_options');
    Route::passkeys();

    Route::get('reset-password', ShowSetNewPassword::class)->name('email.reset-password.show');
    Route::post('reset/password/link', PasswordResetLink::class)->name('password.email');
    Route::patch('reset/password/email', UpdateUserPasswordViaEmail::class)->name('reset-password.email.update');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', Logout::class)->name('logout');
    Route::get('reset/password', ShowSetNewPassword::class)->name('reset-password.edit');
    Route::patch('reset/password', UpdateUserPassword::class)->name('reset-password.update');

});
