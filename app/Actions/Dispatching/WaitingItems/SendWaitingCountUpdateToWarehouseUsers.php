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

class SendWaitingCountUpdateToWarehouseUsers
{
    use AsAction;

    public function handle(Warehouse $warehouse): void
    {
        /** @var User $users */
        $users = User::whereHas('authorisedWarehouses', function ($query) use ($warehouse) {
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
