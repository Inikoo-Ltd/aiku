<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-09h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Auth;

use App\Notifications\MobilePasswordResetCodeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\AsObject;

class SendMobilePasswordResetCode
{
    use AsController;
    use AsObject;
    use WithMobilePasswordResetCode;

    public function handle(string $login): void
    {
        $user = $this->mobilePasswordResetUser($login);
        if (!$user) {
            return;
        }

        $code      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(self::CODE_MINUTES);

        Cache::put($this->mobilePasswordResetCodeKey($user), $this->hashMobilePasswordResetCode($user, $code), $expiresAt);
        Cache::put($this->mobilePasswordResetAttemptsKey($user), 0, $expiresAt);

        $user->notify(new MobilePasswordResetCodeNotification($code, self::CODE_MINUTES));
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $login = trim(Arr::get($request->validated(), 'login'));

        dispatch(fn () => SendMobilePasswordResetCode::run($login))->afterResponse();

        return response()->json([
            'data'    => ['sent' => true],
            'message' => __('If an account matches, we have emailed a 6-digit code to its email address.'),
        ]);
    }
}
