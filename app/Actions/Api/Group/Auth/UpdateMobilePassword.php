<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-09h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Auth;

use App\Actions\SysAdmin\User\UpdateUserPasswordViaEmail;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobilePassword
{
    use AsController;

    public function handle(User $user, string $password, ?int $keepTokenId): void
    {
        UpdateUserPasswordViaEmail::make()->handle($user, ['password' => Hash::make($password)]);
        RevokeMobileTokens::run($user, $keepTokenId);
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password'         => array_values(array_filter(['required', 'string', UpdateUserPasswordViaEmail::passwordRule(), 'confirmed'])),
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($validator->errors()->has('current_password')) {
            return;
        }

        if (!Hash::check((string) $request->input('current_password'), $request->user()->password)) {
            $validator->errors()->add('current_password', __('The current password is incorrect.'));
        }
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user         = $request->user();
        $currentToken = $user->currentAccessToken();

        $this->handle(
            $user,
            Arr::get($request->validated(), 'password'),
            $currentToken instanceof PersonalAccessToken ? $currentToken->id : null
        );

        return response()->json([
            'data' => ['changed' => true],
        ]);
    }
}
