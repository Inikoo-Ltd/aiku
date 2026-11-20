<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;

trait WithMobileOfflineAccess
{
    protected function ensureAuthorisedWarehouse(User $user, Warehouse|int $warehouse): void
    {
        $warehouseId = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;

        abort_unless($user->authorisedWarehouses()->whereKey($warehouseId)->exists(), 404);
    }

    protected function ensureCanHandleOperation(User $user, MobileOfflineOperation $operation): void
    {
        abort_unless(
            $operation->group_id === $user->group_id
            && $operation->device_sequence !== null
            && ($operation->user_id === $user->id || MobileOfflineEngine::isSupervisor($user, $operation->warehouse_id)),
            404
        );
        $this->ensureAuthorisedWarehouse($user, $operation->warehouse_id);
    }

    protected function engine(): MobileOfflineEngine
    {
        return app(MobileOfflineEngine::class);
    }

    protected function presenter(): MobileOfflineOperationPresenter
    {
        return app(MobileOfflineOperationPresenter::class);
    }

    protected function openCount(string $deviceUuid, User $user): int
    {
        return MobileOfflineOperation::query()
            ->where('device_uuid', $deviceUuid)
            ->where('user_id', $user->id)
            ->whereIn('state', MobileOfflineOperation::OPEN_STATES)
            ->count();
    }
}
