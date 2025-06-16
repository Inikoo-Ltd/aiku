<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:10:17 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\CRM\Customer\PreRegisterCustomer;
use App\Actions\CRM\WebUser\Retina\GoogleLoginRetina;
use App\Actions\CRM\WebUser\Retina\LogoutRetina;
use App\Actions\CRM\WebUser\Retina\RetinaLogin;
use App\Actions\CRM\WebUser\Retina\UI\AuthenticateRetinaShopifyUser;
use App\Actions\CRM\WebUser\Retina\UI\ShowStandAloneRegistration;
use App\Actions\CRM\WebUser\Retina\UI\ShowFinishPreRetinaRegister;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaLogin;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaPrepareAccount;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaRegister;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaRegisterChooseMethod;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaResetWebUserPassword;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaResetWebUserPasswordError;
use App\Actions\CRM\WebUser\Retina\UpdateRetinaWebUserPassword;
use App\Actions\Retina\SysAdmin\FinishPreRegisterRetinaCustomer;
use App\Actions\Retina\SysAdmin\PreRegisterRetinaCustomer;
use App\Actions\Retina\SysAdmin\RegisterRetinaFulfilmentCustomer;
use App\Actions\Retina\UI\Auth\SendRetinaResetPasswordEmail;
use App\Actions\Retina\UI\Auth\ShowForgotPasswordForm;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:retina')->group(function () {
    Route::get('auth-shopify', AuthenticateRetinaShopifyUser::class)->name('auth.shopify');

    Route::get('login', ShowRetinaLogin::class)->name('login.show');
    Route::post('login', RetinaLogin::class)->name('login.store');

    Route::post('{shop:id}/register-pre-customer', PreRegisterRetinaCustomer::class)->name('register_pre_customer.store');
    Route::post('login-google', GoogleLoginRetina::class)->name('login_google');
    Route::post('register-google', PreRegisterCustomer::class)->name('register_pre_customer_google.store');


    Route::get('register', ShowRetinaRegisterChooseMethod::class)->name('register_choose_method');
    Route::get('register-step-2', ShowStandAloneRegistration::class)->name('register_standalone');

    Route::get('register-step-3', ShowRetinaRegister::class)->name('register_step_3');

    Route::post('{fulfilment:id}/register', RegisterRetinaFulfilmentCustomer::class)->name('register.store');

    Route::get('rp', ShowRetinaResetWebUserPassword::class)->name('reset-password.show');
    Route::get('reset-password-send', ShowForgotPasswordForm::class)->name('reset-password.edit');
    Route::get('reset-password-error', ShowRetinaResetWebUserPasswordError::class)->name('reset-password.error');
    Route::post('reset-password-send', SendRetinaResetPasswordEmail::class)->name('reset-password.send');
    Route::patch('reset-password', UpdateRetinaWebUserPassword::class)->name('reset-password.update');
});

Route::middleware('retina-auth:retina')->group(function () {
    Route::post('logout', LogoutRetina::class)->name('logout');
    Route::get('prepare-account', ShowRetinaPrepareAccount::class)->name('prepare-account.show');

    Route::get('finish-pre-register', ShowFinishPreRetinaRegister::class)->name('finish_pre_register');
    Route::post('{shop:id}/finish-pre-register', FinishPreRegisterRetinaCustomer::class)->name('finish_pre_register.store');
});
