<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:10:17 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\CRM\WebUser\Retina\GoogleLoginRetina;
use App\Actions\CRM\WebUser\Retina\LogoutRetina;
use App\Actions\CRM\WebUser\Retina\RetinaLogin;
use App\Actions\CRM\WebUser\Retina\UI\AuthenticateRetinaShopifyUser;
use App\Actions\CRM\WebUser\Retina\UI\ShowStandAloneRegistration;
use App\Actions\CRM\WebUser\Retina\UI\ShowFinishPreRetinaRegister;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaLogin;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaPrepareAccount;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaRegisterWithGoogle;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaRegisterChooseMethod;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaResetWebUserPassword;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaResetWebUserPasswordError;
use App\Actions\CRM\WebUser\Retina\UpdateRetinaWebUserPassword;
use App\Actions\Retina\SysAdmin\RegisterRetinaFromGoogle;
use App\Actions\Retina\SysAdmin\RegisterRetinaFromStandalone;
use App\Actions\Retina\UI\Auth\SendRetinaResetPasswordEmail;
use App\Actions\Retina\UI\Auth\ShowForgotPasswordForm;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:retina')->group(function () {


    Route::post('login', RetinaLogin::class)->name('login.store');
    Route::post('login-google', GoogleLoginRetina::class)->name('login_google');


    Route::post('register-from-google', RegisterRetinaFromGoogle::class)->name('register_from_google.store');
    Route::post('register-from-standalone', RegisterRetinaFromStandalone::class)->name('register_from_standalone.store');

    Route::post('reset-password-send', SendRetinaResetPasswordEmail::class)->name('reset-password.send');
    Route::patch('reset-password', UpdateRetinaWebUserPassword::class)->name('reset-password.update');


    Route::get('auth-shopify', AuthenticateRetinaShopifyUser::class)->name('auth.shopify');

    Route::get('login', ShowRetinaLogin::class)->name('login.show');


    Route::get('register', ShowRetinaRegisterChooseMethod::class)->name('register');
    Route::get('registration-form', ShowStandAloneRegistration::class)->name('register_standalone');

    Route::get('register-from-google', ShowRetinaRegisterWithGoogle::class)->name('register_from_google');

    Route::get('rp', ShowRetinaResetWebUserPassword::class)->name('reset-password.show');
    Route::get('reset-password-send', ShowForgotPasswordForm::class)->name('reset-password.edit');
    Route::get('reset-password-error', ShowRetinaResetWebUserPasswordError::class)->name('reset-password.error');
});

Route::middleware('retina-auth:retina')->group(function () {
    Route::post('logout', LogoutRetina::class)->name('logout');
    Route::get('prepare-account', ShowRetinaPrepareAccount::class)->name('prepare-account.show');

    Route::get('finish-pre-register', ShowFinishPreRetinaRegister::class)->name('finish_pre_register');
});
