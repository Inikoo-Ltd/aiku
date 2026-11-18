<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Throwable;

trait WithMobileFulfilmentOutbound
{
    use WithMobileFulfilment;

    public const int MAX_ID = 2147483647;
    public const int MAX_PER_PAGE = 100;
    public const int DEFAULT_PER_PAGE = 25;

    protected function ensureCanViewFulfilment(User $user, Warehouse $warehouse): void
    {
        $this->ensureFulfilmentAccess($user, $warehouse, self::canViewFulfilment($user, $warehouse));
    }

    protected function underLock(string $model, int $id, Closure $callback): mixed
    {
        try {
            return Cache::lock("mobile-fulfilment:$model:$id", 10)->block(5, $callback);
        } catch (LockTimeoutException) {
            $this->refuseState(__('Someone else is changing this right now. Try again in a moment.'));
        }
    }

    /**
     * @throws Throwable
     */
    protected function oncePerRequest(User $user, ?string $clientRequestId, Closure $work, Closure $repeat): mixed
    {
        if ($clientRequestId === null) {
            return $work();
        }

        $cacheKey = 'mobile-fulfilment-request:'.$user->id.':'.strtolower($clientRequestId);
        if (!Cache::add($cacheKey, true, now()->addDay())) {
            return $repeat();
        }

        try {
            return $work();
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }

    /**
     * @param array<int, string> $sorts
     * @return array<int, string>
     */
    protected function sortOptions(array $sorts): array
    {
        return collect($sorts)->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all();
    }

    /**
     * @param array{per_page?: int|string|null} $filters
     */
    protected function perPage(array $filters): int
    {
        return min((int) ($filters['per_page'] ?? 0), self::MAX_PER_PAGE) ?: self::DEFAULT_PER_PAGE;
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ];
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    protected function userReference(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->contact_name ?: $user->username] : null;
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    protected function customerReference(?FulfilmentCustomer $fulfilmentCustomer): ?array
    {
        return $fulfilmentCustomer ? ['id' => $fulfilmentCustomer->id, 'name' => $fulfilmentCustomer->customer?->name] : null;
    }

    /**
     * @return array{id: int, code: string}|null
     */
    protected function locationReference(?Location $location): ?array
    {
        return $location ? ['id' => $location->id, 'code' => $location->code] : null;
    }

    protected function palletTypeLabel(PalletTypeEnum $type): string
    {
        return match ($type) {
            PalletTypeEnum::PALLET   => __('Pallet'),
            PalletTypeEnum::BOX      => __('Box'),
            PalletTypeEnum::OVERSIZE => __('Oversize'),
        };
    }
}
