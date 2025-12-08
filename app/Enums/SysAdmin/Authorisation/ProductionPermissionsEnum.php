<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\Authorisation;

use App\Models\Production\Production;

enum ProductionPermissionsEnum: string
{
    case PRODUCTION_OPERATIONS = 'productions_operations';

    case PRODUCTION_OPERATIONS_EDIT = 'productions_operations.edit';

    case PRODUCTION_OPERATIONS_VIEW = 'productions_operations.view';

    case PRODUCTION_OPERATIONS_ORCHESTRATE = 'productions_operations.orchestrate';

    case PRODUCTION_RD = 'productions_rd';

    case PRODUCTION_RD_VIEW = 'productions_rd.view';

    case PRODUCTION_RD_EDIT = 'productions_rd.edit';

    case PRODUCTION_PROCUREMENT = 'productions_procurement';
    case PRODUCTION_PROCUREMENT_EDIT = 'productions_procurement.edit';
    case PRODUCTION_PROCUREMENT_VIEW = 'productions_procurement.view';

    public static function getAllValues(Production $production): array
    {

        $rawPermissionsNames = array_column(ProductionPermissionsEnum::cases(), 'value');

        $permissionsNames = [];
        foreach ($rawPermissionsNames as $rawPermissionsName) {
            $permissionsNames[] = self::getPermissionName($rawPermissionsName, $production);
        }

        return $permissionsNames;
    }

    public static function getPermissionName(string $rawName, Production $production): string
    {
        $permissionComponents = explode('.', $rawName);
        $permissionComponents = array_merge(array_slice($permissionComponents, 0, 1), [$production->id], array_slice($permissionComponents, 1));

        return implode('.', $permissionComponents);
    }
}
