<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-09h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Auth;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait WithMobilePasswordResetCode
{
    public const int CODE_MINUTES = 15;

    public const int MAX_CODE_ATTEMPTS = 5;

    protected function mobilePasswordResetUser(string $login): ?User
    {
        $activeUsersWithEmail = fn (): Builder => User::query()
            ->where('status', true)
            ->whereNotNull('email')
            ->where('email', '!=', '');

        return $activeUsersWithEmail()->where('username', $login)->first()
            ?? $activeUsersWithEmail()->whereRaw('lower(email) = ?', [Str::lower($login)])->orderBy('id')->first();
    }

    protected function mobilePasswordResetCodeKey(User $user): string
    {
        return 'mobile-password-reset-code:'.$user->id;
    }

    protected function mobilePasswordResetAttemptsKey(User $user): string
    {
        return 'mobile-password-reset-code-attempts:'.$user->id;
    }

    protected function hashMobilePasswordResetCode(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$code, (string) config('app.key'));
    }
}
