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
    abstract protected function permission(): ShopPermissionsEnum;

    protected function authorisedShop(Request $request): ?Shop
    {
        $shop = Shop::where('slug', $request->string('shop'))->first();

        if (!$shop) {
            return null;
        }

        $permissionName = ShopPermissionsEnum::getPermissionName($this->permission()->value, $shop);

        return $request->user()->authTo($permissionName) ? $shop : null;
    }
}
