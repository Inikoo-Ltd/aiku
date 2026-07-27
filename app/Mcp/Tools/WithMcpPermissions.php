<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Models\SysAdmin\User;
use Laravel\Mcp\Request;

trait WithMcpPermissions
{
    /**
     * Spatie permissions are team scoped: without the team id every check returns
     * false and authTo() then caches that false for a week. MCP requests do not
     * carry the group binding the web middleware provides, so set it explicitly.
     */
    protected function userCan(Request $request, string $permission): bool
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user) {
            return false;
        }

        setPermissionsTeamId($user->group_id);

        \Illuminate\Support\Facades\Log::info('MCPDEBUG', [
            'permission'  => $permission,
            'user_id'     => $user->id,
            'user_class'  => get_class($user),
            'group_id'    => $user->group_id,
            'team_id'     => getPermissionsTeamId(),
            'hasPerm'     => $user->hasPermissionTo($permission),
            'authTo'      => $user->authTo($permission),
            'roles_count' => $user->roles()->count(),
        ]);

        return $user->authTo($permission);
    }
}
