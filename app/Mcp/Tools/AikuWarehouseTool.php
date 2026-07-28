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
    use WithMcpPermissions;

    /**
     * Users with direct SQL access work against the database instead: hiding the
     * purpose-built tools from them keeps the choice on our side rather than
     * relying on the assistant to pick correctly.
     */
    public function shouldRegister(Request $request): bool
    {
        return !$request->user()?->can_use_mcp_sql;
    }

    abstract protected function permission(): WarehousePermissionsEnum;

    protected function authorisedWarehouse(Request $request): ?Warehouse
    {
        $warehouse = Warehouse::where(function ($query) use ($request) {
            $identifier = strtolower((string) $request->string('warehouse'));
            $query->whereRaw('lower(slug) = ?', [$identifier])
                ->orWhereRaw('lower(code) = ?', [$identifier]);
        })->first();

        if (!$warehouse) {
            return null;
        }

        $permissionName = WarehousePermissionsEnum::getPermissionName($this->permission()->value, $warehouse);

        return $this->userCan($request, $permissionName) ? $warehouse : null;
    }
}
