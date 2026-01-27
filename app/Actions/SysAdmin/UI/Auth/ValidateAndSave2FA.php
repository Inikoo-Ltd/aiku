<?php

/*
 * author Louis Perez
 * created on 20-01-2026-14h-48m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\SysAdmin\UI\Auth;

use App\Actions\UI\Profile\UpdateProfile;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use PragmaRX\Google2FAQRCode\Google2FA;
use Inertia\Inertia;
use App\Actions\GrpAction;

class ValidateAndSave2FA extends GrpAction
{
    use AsController;

    private string $gate = 'web';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ActionRequest $request, array $modelData): RedirectResponse|Response
    {
        $authenticator = new Authenticator(request());
        $google2fa = new Google2FA();
        $secret = Arr::get($modelData, 'secret_key');
        $otp = Arr::get($modelData, 'one_time_password');
        $validated = $google2fa->verifyKey($secret, $otp);

        if (!$validated) {
            throw ValidationException::withMessages([
                'one_time_password' => trans('Invalid OTP is given. Please check your Authenticator App'),
            ]);
        }
        UpdateProfile::run($request->user(), [
            'enable_2fa'    => [
                'has_2fa'   => true,
                'secretKey' => $secret
            ]
        ]);

        $authenticator->login();

        return Inertia::location(route('grp.dashboard.show'));
    }

    public function rules(): array
    {
        return [
            'one_time_password' => ['required', 'string'],
            'secret_key'            => ['required', 'string']
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(ActionRequest $request): RedirectResponse|Response
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($request, $this->validatedData);
    }

}
