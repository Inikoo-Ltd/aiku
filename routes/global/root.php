<?php

use App\Actions\UI\Global\PreRegisterRetinaDropshippingCustomer;
use Laravel\Socialite\Facades\Socialite;

Route::post('/register-pre-customer/{shop:slug}', PreRegisterRetinaDropshippingCustomer::class)->name('register-pre-customer.store');

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
