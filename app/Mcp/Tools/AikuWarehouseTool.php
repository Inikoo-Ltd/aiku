<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\Warehouse;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

abstract class AikuWarehouseTool extends Tool
{
    abstract protected function permission(): WarehousePermissionsEnum;

    protected function authorisedWarehouse(Request $request): ?Warehouse
    {
        $warehouse = Warehouse::where('slug', $request->string('warehouse'))->first();

        if (!$warehouse) {
            return null;
        }

        $permissionName = WarehousePermissionsEnum::getPermissionName($this->permission()->value, $warehouse);

        return $request->user()->authTo($permissionName) ? $warehouse : null;
    }
}
