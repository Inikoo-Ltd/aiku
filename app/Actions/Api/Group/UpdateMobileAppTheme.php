<?php

/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Actions\Api\Group\Resources\ProfileApiResource;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class UpdateMobileAppTheme
{
    use WithActionUpdate;

    public function handle(User $user, array $mobileAppTheme): User
    {
        return $this->update($user, ['settings' => ['mobile_app_theme' => array_values($mobileAppTheme)]], ['settings']);
    }

    public function rules(): array
    {
        return [
            'mobile_app_theme'   => ['required', 'array', 'size:8'],
            'mobile_app_theme.*' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function asController(ActionRequest $request): User
    {
        $request->validate();

        /** @var User $user */
        $user = $request->user();

        return $this->handle($user, $request->validated('mobile_app_theme'));
    }

    public function jsonResponse(User $user): ProfileApiResource
    {
        $user->load([
            'employees',
            'guests',
        ]);

        return ProfileApiResource::make($user);
    }
}
