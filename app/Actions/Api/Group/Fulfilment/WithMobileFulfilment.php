<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Api\Group\WithMobileModuleAccess;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

trait WithMobileFulfilment
{
    use WithMobileModuleAccess;

    public static function canEditFulfilmentWarehouse(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && $user->authTo([
            "fulfilment.$warehouse->id.edit",
            "supervisor-incoming.$warehouse->id",
            "supervisor-fulfilment.$warehouse->id",
        ]);
    }

    public static function canEditFulfilmentShop(User $user, int $fulfilmentId): bool
    {
        return $user->authTo("fulfilment-shop.$fulfilmentId.edit");
    }

    public static function canEditFulfilmentWarehouseOrShop(User $user, Warehouse $warehouse, int $fulfilmentId): bool
    {
        return static::canEditFulfilmentWarehouse($user, $warehouse) || static::canEditFulfilmentShop($user, $fulfilmentId);
    }

    protected function ensureFulfilmentAccess(User $user, Warehouse $warehouse, bool $allowed, ?string $refusal = null): void
    {
        abort_unless($allowed, 403, $refusal ?? __('You do not have access to fulfilment on this warehouse.'));
        abort_unless($user->authorisedWarehouses()->whereKey($warehouse->id)->exists(), 404);
    }

    protected function refuseState(string $message, string $field = 'state'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    protected function isoDateTime(DateTimeInterface|string|null $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }

    /**
     * @return array{key: string, label: string}
     */
    protected function actionOption(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label];
    }
}
