<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Models\SysAdmin\Organisation;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

abstract class AikuOrganisationTool extends Tool
{
    abstract protected function permission(): OrganisationPermissionsEnum;

    protected function authorisedOrganisation(Request $request): ?Organisation
    {
        $organisation = Organisation::where('slug', $request->string('organisation'))->first();

        if (!$organisation) {
            return null;
        }

        $permissionName = OrganisationPermissionsEnum::getPermissionName($this->permission()->value, $organisation);

        return $request->user()->authTo($permissionName) ? $organisation : null;
    }
}
