<?php

/*
 * author Louis Perez
 * created on 13-01-2026-15h-12m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\SysAdmin\UI\Auth;

use Illuminate\Support\Arr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use PragmaRX\Google2FAQRCode\Google2FA;
use Inertia\Inertia;

class Validate2FA
{
    use AsController;

    private string $gate = 'web';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ActionRequest $request): RedirectResponse
    {
        $google2fa = new Google2FA();
        $otp = Arr::get($request->validated(), 'one_time_password');

        $validated = $google2fa->verifyKey(auth()->user()->google2fa_secret, $otp);

        if (!$validated) {
            throw ValidationException::withMessages([
                'one_time_password' => trans('Invalid OTP is given. Please check your Authenticator App'),
            ]);
        }
        
        return Inertia::location(route('grp.dashboard.show'));
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
    public function asController(ActionRequest $request): RedirectResponse | array
    {
        return $this->handle($request);
    }

}
