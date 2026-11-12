<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Oct 2023 15:27:31 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserPasswordViaEmail
{
    use WithActionUpdate;

    private bool $asAction = false;

    public function handle(User $user, array $modelData): User
    {
        data_set($modelData, 'reset_password', false);
        return $this->update($user, $modelData, 'settings');
    }


    public static function passwordRule(): ?PasswordRule
    {
        return app()->isLocal() || app()->environment('testing') ? null : PasswordRule::min(8);
    }

    public function rules(): array
    {
        return [
            'password' => ['required', self::passwordRule()],
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email']
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(ActionRequest $request): void
    {
        $request->validate();

        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'token'),
            function (User $user, string $password) {
                $this->handle($user, [
                    'password'       => Hash::make($password),
                    'reset_password' => false
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => __('This password reset link is invalid or has expired.')]);
        }
    }

    public function action(User $user, $objectData): User
    {
        $this->asAction = true;
        $this->setRawAttributes($objectData);
        $validatedData = $this->validateAttributes();

        return $this->handle($user, $validatedData);
    }

    public function htmlResponse(): Response
    {
        Session::put('reloadLayout', '1');

        return Inertia::location(route('grp.dashboard.show'));
    }
}
