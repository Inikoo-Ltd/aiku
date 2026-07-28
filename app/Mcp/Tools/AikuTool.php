<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Shop;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

abstract class AikuTool extends Tool
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

    abstract protected function permission(): ShopPermissionsEnum;

    protected function authorisedShop(Request $request): ?Shop
    {
        $shop = Shop::where(function ($query) use ($request) {
            $identifier = strtolower((string) $request->string('shop'));
            $query->whereRaw('lower(slug) = ?', [$identifier])
                ->orWhereRaw('lower(code) = ?', [$identifier]);
        })->first();

        if (!$shop) {
            return null;
        }

        $permissionName = ShopPermissionsEnum::getPermissionName($this->permission()->value, $shop);

        return $this->userCan($request, $permissionName) ? $shop : null;
    }
}
