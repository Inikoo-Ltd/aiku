<?php

/*
 * Author: Vika Aqordi
 * Created on 23-04-2026-15h-33m
 * GitHub: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\WaitingItems;

use App\Events\BroadcastWaitingCountUpdate;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendWaitingCountUpdateToWarehouseUsers implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(int|null $warehouseId): string
    {
        return $warehouseId ?? 'empty';
    }

    public function handle(?int $warehouseId): void
    {
        if (!$warehouseId) {
            return;
        }

        $warehouse = Warehouse::on('aiku_no_sticky')->find($warehouseId);
        if (!$warehouse) {
            return;
        }

        /** @var User $users */
        $users = User::on('aiku_no_sticky')->where('status', true)->whereHas('authorisedWarehouses', function ($query) use ($warehouse) {
            $query->where('model_id', $warehouse->id);
        })->get();

        foreach ($users as $user) {
            if (!$user->authTo("dispatching.$warehouse->id.view")) {
                continue;
            }

            BroadcastWaitingCountUpdate::dispatch(
                $user->id,
                GetDispatchingWaitingBadgeData::make()->totalCount($user),
                GetCrmWaitingBadgeData::make()->totalCount($user)
            );
        }
    }
}
