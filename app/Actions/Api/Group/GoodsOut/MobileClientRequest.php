<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MobileClientRequest
{
    public static function key(User $user, string $clientRequestId): string
    {
        return 'mobile-write:'.$user->id.':'.strtolower($clientRequestId);
    }

    /**
     * @template T
     * @param Closure(): T $write
     * @param Closure(): T $alreadyDone
     * @return T
     * @throws Throwable
     */
    public static function once(User $user, ?string $clientRequestId, Closure $write, Closure $alreadyDone): mixed
    {
        if (!$clientRequestId) {
            return $write();
        }

        $key = self::key($user, $clientRequestId);
        if (!Cache::add($key, true, now()->addDay())) {
            return $alreadyDone();
        }

        try {
            return $write();
        } catch (Throwable $exception) {
            Cache::forget($key);

            throw $exception;
        }
    }

    public static function isDone(User $user, ?string $clientRequestId): bool
    {
        return $clientRequestId && Cache::has(self::key($user, $clientRequestId));
    }

    public static function markDoneAfterCommit(User $user, ?string $clientRequestId): void
    {
        if (!$clientRequestId) {
            return;
        }

        $key = self::key($user, $clientRequestId);
        DB::afterCommit(fn () => Cache::put($key, true, now()->addDay()));
    }
}
