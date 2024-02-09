<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:10:17 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */



use App\Actions\SysAdmin\WebUser\Logout;
use App\Actions\SysAdmin\WebUser\UI\ShowLogin;
use App\Actions\SysAdmin\WebUser\UI\ShowResetWebUserPassword;
use App\Actions\SysAdmin\WebUser\UpdateWebUserPassword;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:customer')->group(function () {
    Route::get('login', ShowLogin::class)->name('login');
    //Route::post('login', Login::class)->name('login.store');
    //Route::get('register', ShowRegister::class)->name('register');
    //Route::post('register', Register::class);

});

Route::middleware('auth')->group(function () {
    Route::post('logout', Logout::class)->name('logout');
    Route::get('reset/password', ShowResetWebUserPassword::class)->name('reset-password.edit');
    Route::patch('reset/password', UpdateWebUserPassword::class)->name('reset-password.update');

});
