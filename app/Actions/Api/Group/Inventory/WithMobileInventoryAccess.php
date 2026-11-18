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

    protected function ensureCanViewInventory(User $user, Warehouse $warehouse): void
    {
        abort_unless(self::canViewInventory($user, $warehouse), 403);
    }
}
