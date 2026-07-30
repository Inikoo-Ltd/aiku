<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\SysAdmin\Group;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

abstract class AikuGroupTool extends Tool
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

    abstract protected function permission(): GroupPermissionsEnum;

    protected function authorisedGroup(Request $request): ?Group
    {
        $group = $request->user()->group;

        if (!$group) {
            return null;
        }

        return $this->userCan($request, $this->permission()->value) ? $group : null;
    }
}
