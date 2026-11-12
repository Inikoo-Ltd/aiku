<?php

/*
 * author Louis Perez
 * created on 20-01-2026-14h-48m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\SysAdmin\UI\Auth;

use App\Models\SysAdmin\User;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use PragmaRX\Google2FAQRCode\Google2FA;
use Inertia\Inertia;
use App\Actions\OrgAction;

class ValidateAndSave2FA extends OrgAction
{
    use AsController;

    private string $gate = 'web';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ActionRequest $request, array $modelData): RedirectResponse|Response
    {
        if ($request->user()->google2fa_secret) {
            abort(403);
        }

        $authenticator = new Authenticator(request());
        $this->saveVerifiedSecret($request->user(), Arr::get($modelData, 'secret_key'), Arr::get($modelData, 'one_time_password'));

        $authenticator->login();

        return Inertia::location(route('grp.dashboard.show'));
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function saveVerifiedSecret(User $user, string $secret, string $oneTimePassword): void
    {
        if (!(new Google2FA())->verifyKey($secret, $oneTimePassword)) {
            throw ValidationException::withMessages([
                'one_time_password' => trans('Invalid OTP is given. Please check your Authenticator App'),
            ]);
        }

        $user->update(['google2fa_secret' => $secret]);
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
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($request, $this->validatedData);
    }

}
