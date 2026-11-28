<?php

/*
 * author Louis Perez
 * created on 13-01-2026-15h-12m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\SysAdmin\UI\Auth;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use PragmaRX\Google2FAQRCode\Google2FA;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use Symfony\Component\HttpFoundation\Response;

class Validate2FA
{
    use AsController;

    private string $gate = 'web';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ActionRequest $request): Response
    {
        $throttleKey = '2fa:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'one_time_password' => __('Too many wrong codes. Please try again in :minutes minutes.', [
                    'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        $authenticator = new Authenticator(request());
        $google2fa = new Google2FA();
        $otp = Arr::get($request->validated(), 'one_time_password');
        $secret = $request->user()->google2fa_secret;
        $validated = $google2fa->verifyKey($secret, $otp);

        if (!$validated) {
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages([
                'one_time_password' => trans('Invalid OTP is given. Please check your Authenticator App'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $authenticator->login();

        Session::put('reloadLayout', '1');

        return ProcessSuccessfulLogin::leaveInertiaForOauth(redirect()->intended(route('grp.dashboard.show')));
    }

    public function rules(): array
    {
        return [
            'one_time_password' => ['required', 'string'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(ActionRequest $request): Response
    {
        return $this->handle($request);
    }

}
