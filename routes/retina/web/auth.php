<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:10:17 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */


use App\Actions\CRM\WebUser\Retina\LogoutRetina;
use App\Actions\CRM\WebUser\Retina\RetinaLogin;
use App\Actions\CRM\WebUser\Retina\UI\AuthenticateRetinaShopifyUser;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaLogin;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaPrepareAccount;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaRegister;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaResetWebUserPassword;
use App\Actions\CRM\WebUser\Retina\UI\ShowRetinaResetWebUserPasswordError;
use App\Actions\CRM\WebUser\Retina\UpdateRetinaWebUserPassword;
use App\Actions\Retina\SysAdmin\PreRegisterRetinaDropshippingCustomer;
use App\Actions\Retina\SysAdmin\RegisterRetinaDropshippingCustomer;
use App\Actions\Retina\SysAdmin\RegisterRetinaFulfilmentCustomer;
use App\Actions\Retina\UI\Auth\SendRetinaResetPasswordEmail;
use App\Actions\Retina\UI\Auth\ShowForgotPasswordForm;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:retina')->group(function () {
    Route::get('auth-shopify', AuthenticateRetinaShopifyUser::class)->name('auth.shopify');

    Route::get('login', ShowRetinaLogin::class)->name('login.show');
    Route::post('login', RetinaLogin::class)->name('login.store');

    Route::get('/login/google', function () {
        return Socialite::driver('google')->scopes(['email', 'profile'])->redirect();
    })->name('login.google');

    Route::get('/auth/google/callback', function () {
        $googleUser = Socialite::driver('google')->user();
        session(['subscribe_with_google' => [
            'id' => $googleUser->id,
            'name' => $googleUser->name,
            'email' => $googleUser->email,
            'avatar' => $googleUser->avatar,
        ]]);

        // To forget only the specific session data
        // session()->forget('subscribe_with_google');

        // Or to flush the entire session
        return redirect()->route('iris.iris_webpage');
    });

    Route::post('register-pre-customer', PreRegisterRetinaDropshippingCustomer::class)->name('register-pre-customer.store');
    Route::get('register', ShowRetinaRegister::class)->name('register');
    Route::post('{fulfilment:id}/register', RegisterRetinaFulfilmentCustomer::class)->name('register.store');
    Route::post('ds/{shop:id}/register', RegisterRetinaDropshippingCustomer::class)->name('ds.register.store');

    Route::get('rp', ShowRetinaResetWebUserPassword::class)->name('reset-password.show');
    Route::get('reset-password-send', ShowForgotPasswordForm::class)->name('reset-password.edit');
    Route::get('reset-password-error', ShowRetinaResetWebUserPasswordError::class)->name('reset-password.error');
    Route::post('reset-password-send', SendRetinaResetPasswordEmail::class)->name('reset-password.send');
    Route::patch('reset-password', UpdateRetinaWebUserPassword::class)->name('reset-password.update');
});

Route::middleware('retina-auth:retina')->group(function () {
    Route::post('logout', LogoutRetina::class)->name('logout');
    Route::get('prepare-account', ShowRetinaPrepareAccount::class)->name('prepare-account.show');
});
