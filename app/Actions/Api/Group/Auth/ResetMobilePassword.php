<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-09h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Auth;

use App\Actions\SysAdmin\User\UpdateUserPasswordViaEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ResetMobilePassword
{
    use AsController;
    use WithMobilePasswordResetCode;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(string $login, string $code, string $password): void
    {
        $user     = $this->mobilePasswordResetUser($login);
        $codeHash = $user ? Cache::get($this->mobilePasswordResetCodeKey($user)) : null;

        if ($codeHash === null) {
            throw ValidationException::withMessages(['code' => __('The code is wrong or has expired.')]);
        }

        $attempt = Cache::increment($this->mobilePasswordResetAttemptsKey($user));
        if ($attempt > self::MAX_CODE_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => __('Too many wrong codes. Ask for a new code.')]);
        }

        if (!hash_equals($codeHash, $this->hashMobilePasswordResetCode($user, $code))) {
            throw ValidationException::withMessages([
                'code' => $attempt >= self::MAX_CODE_ATTEMPTS ? __('Too many wrong codes. Ask for a new code.') : __('The code is wrong or has expired.'),
            ]);
        }

        Cache::forget($this->mobilePasswordResetCodeKey($user));
        Cache::forget($this->mobilePasswordResetAttemptsKey($user));

        UpdateUserPasswordViaEmail::make()->handle($user, ['password' => Hash::make($password)]);
        RevokeMobileTokens::run($user);
    }

    public function rules(): array
    {
        return [
            'login'    => ['required', 'string', 'max:255'],
            'code'     => ['required', 'string', 'max:255'],
            'password' => array_values(array_filter(['required', 'string', UpdateUserPasswordViaEmail::passwordRule(), 'confirmed'])),
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->handle(trim(Arr::get($validated, 'login')), trim(Arr::get($validated, 'code')), Arr::get($validated, 'password'));

        return response()->json([
            'data' => ['reset' => true],
        ]);
    }
}
