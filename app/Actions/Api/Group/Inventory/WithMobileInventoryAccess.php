<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Api\Group\Locations\WithMobileLocationsAccess;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;

trait WithMobileInventoryAccess
{
    use WithMobileLocationsAccess;

    /**
     * The view rights the web Inventory pages check (WithInventoryAuthorisation).
     */
    public static function canViewInventory(User $user, Warehouse $warehouse): bool
    {
        if ($warehouse->group_id !== $user->group_id) {
            return false;
        }

        $organisation = $warehouse->organisation;
        $permissions  = ["inventory.$organisation->id.view", "accounting.$organisation->id.view"];

        foreach ($organisation->warehouses()->pluck('id') as $warehouseId) {
            $permissions[] = "supervisor-stocks.$warehouseId.view";
            $permissions[] = "stocks.$warehouseId.view";
            $permissions[] = "fulfilment.view.$warehouseId.view";
        }

        return $user->authTo($permissions);
    }

    protected function ensureCanViewInventory(User $user, Warehouse $warehouse): void
    {
        abort_unless(self::canViewInventory($user, $warehouse), 403);
    }
}
