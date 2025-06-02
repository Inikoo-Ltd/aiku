<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:10:17 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\CRM\Customer\StorePreRegisterCustomer;
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
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:retina')->group(function () {
    Route::get('auth-shopify', AuthenticateRetinaShopifyUser::class)->name('auth.shopify');

    Route::get('login', ShowRetinaLogin::class)->name('login.show');
    Route::post('login', RetinaLogin::class)->name('login.store');

    Route::post('/register-pre-customer/{shop:slug}', PreRegisterRetinaDropshippingCustomer::class)->name('register-pre-customer.store');

    Route::get('/{shop:slug}/login/google', function (Shop $shop) {
        return Socialite::driver('google')->with(['shop' => $shop])->scopes(['email', 'profile'])->redirect();
    })->name('login.google');

    Route::get('/auth/google/callback', function (Request $request) {
        $googleUser = Socialite::driver('google')->user();
        $shop = $request->input('shop');

        StorePreRegisterCustomer::run($shop, [
            'email' => $googleUser->email,
            'name' => $googleUser->name,
            'google_id' => $googleUser->id,
            'avatar' => $googleUser->avatar,
        ]);

        return redirect()->route('iris.iris_webpage');
    });


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
