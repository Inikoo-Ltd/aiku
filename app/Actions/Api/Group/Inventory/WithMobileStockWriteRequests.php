<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Throwable;

trait WithMobileStockWriteRequests
{
    public static function mobileWriteKey(int $userId, string $clientRequestId): string
    {
        return 'mobile-write:'.$userId.':'.strtolower($clientRequestId);
    }

    /**
     * @param array<string, mixed> $input
     */
    protected function optionalClientRequestId(array $input): ?string
    {
        $data = Validator::make($input, [
            'client_request_id' => ['sometimes', 'nullable', 'uuid'],
        ])->validate();

        return $data['client_request_id'] ?? null;
    }

    /**
     * @template T
     * @param Closure(): T $write
     * @param Closure(): T $alreadyDone
     * @return T
     *
     * @throws Throwable
     */
    protected function oncePerClientRequest(User $user, ?string $clientRequestId, Closure $write, Closure $alreadyDone): mixed
    {
        if ($clientRequestId === null) {
            return $write();
        }

        $cacheKey = self::mobileWriteKey($user->id, $clientRequestId);
        if (!Cache::add($cacheKey, true, now()->addDay())) {
            return $alreadyDone();
        }

        try {
            return $write();
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }
}
