<?php

/*
 * author Louis Perez
 * created on 15-01-2026-10h-44m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

use App\Actions\SysAdmin\UI\Auth\Show2FA;
use App\Actions\SysAdmin\UI\Auth\Show2FARequired;
use App\Actions\SysAdmin\UI\Auth\Validate2FA;
use App\Actions\SysAdmin\UI\Auth\ValidateAndSave2FA;
use Illuminate\Support\Facades\Route;
use App\Actions\UI\Profile\View2FAProfile;

Route::middleware('auth')->group(function () {
    Route::get('profile/2fa-qrcode', View2FAProfile::class)->name('profile.2fa-qrcode');

    Route::get('two-factor', Show2FA::class)->name('login.show2fa');
    Route::post('two-factor', Validate2FA::class)->name('login.auth2fa');
    
    Route::get('two-factor/required', Show2FARequired::class)->name('login.require2fa');
    Route::post('two-factor/validate-save', ValidateAndSave2FA::class)->name('login.validate_save2fa');
});