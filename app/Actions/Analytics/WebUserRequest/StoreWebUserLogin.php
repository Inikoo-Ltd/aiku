<?php

/*
 * author Arya Permana - Kirin
 * created on 20-11-2024-16h-30m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Analytics\WebUserRequest;

use App\Actions\GrpAction;
use App\Models\CRM\WebUser;
use App\Models\WebUserLogin;
use Lorisleiva\Actions\ActionRequest;

class StoreWebUserLogin extends GrpAction
{
    public function handle(WebUser $webUser, array $modelData): WebUserLogin
    {
        /** @var WebUserLogin $webUserLogin */
        $webUserLogin = $webUser->webUserLogins()->create($modelData);

        return $webUserLogin;
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

    public function action(WebUser $webUser, array $modelData, int $hydratorsDelay = 0): WebUserLogin
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($webUser->group, $modelData);

        return $this->handle($webUser, $this->validatedData);
    }
}
