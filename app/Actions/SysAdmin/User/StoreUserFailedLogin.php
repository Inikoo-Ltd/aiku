<?php

/*
 * author Arya Permana - Kirin
 * created on 20-11-2024-16h-30m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\User;

use App\Actions\GrpAction;
use App\Models\SysAdmin\User;
use App\Models\UserFailedLogIn;
use Lorisleiva\Actions\ActionRequest;

class StoreUserFailedLogin extends GrpAction
{
    public function handle(User $user, array $modelData): UserFailedLogIn
    {
        /** @var UserFailedLogIn $failedUserLogin */
        $failedUserLogin = $user->userFailedLogins()->create($modelData);

        return $failedUserLogin;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:100'],
            'datetime' => ['required', 'date'],
            'username' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'ip_address' => ['nullable', 'ip'],
            'location' => ['nullable', 'json'],
            'user_agent' => ['nullable', 'string'],
            'device_type' => ['nullable', 'json'],
            'platform' => ['nullable', 'json'],
            'browser' => ['nullable', 'json']
        ];
    }

    public function action(User $user, array $modelData, int $hydratorsDelay = 0): UserFailedLogIn
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($user->group, $modelData);

        return $this->handle($user, $this->validatedData);
    }
}
