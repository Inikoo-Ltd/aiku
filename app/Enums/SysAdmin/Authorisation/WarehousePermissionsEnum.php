<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jan 2024 20:08:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\Authorisation;

use App\Models\Inventory\Warehouse;

enum WarehousePermissionsEnum: string
{
    case LOCATIONS = 'locations';

    case LOCATIONS_EDIT = 'locations.edit';

    case LOCATIONS_VIEW = 'locations.view';

    case STOCKS = 'stocks';
    case STOCKS_EDIT = 'stocks.edit';
    case STOCKS_VIEW = 'stocks.view';

    case INCOMING = 'incoming';
    case INCOMING_EDIT = 'incoming.edit';
    case INCOMING_VIEW = 'incoming.view';

    case DISPATCHING = 'dispatching';
    case DISPATCHING_EDIT = 'dispatching.edit';
    case DISPATCHING_VIEW = 'dispatching.view';

    case FULFILMENT = 'fulfilment';

    case FULFILMENT_VIEW = 'fulfilment.view';

    case FULFILMENT_EDIT = 'fulfilment.edit';

    case SUPERVISOR_LOCATIONS = 'supervisor-locations';
    case SUPERVISOR_STOCKS = 'supervisor-stocks';
    case SUPERVISOR_DISPATCHING = 'supervisor-dispatching';
    case SUPERVISOR_INCOMING = 'supervisor-incoming';
    case SUPERVISOR_FULFILMENT = 'supervisor-fulfilment';

    public static function getAllValues(Warehouse $warehouse): array
    {
        $rawPermissionsNames = array_column(WarehousePermissionsEnum::cases(), 'value');

        $permissionsNames = [];
        foreach ($rawPermissionsNames as $rawPermissionsName) {
            $permissionsNames[] = self::getPermissionName($rawPermissionsName, $warehouse);
        }

        return $permissionsNames;
    }

    public static function getPermissionName(string $rawName, Warehouse $warehouse): string
    {
        $permissionComponents = explode('.', $rawName);
        $permissionComponents = array_merge(array_slice($permissionComponents, 0, 1), [$warehouse->id], array_slice($permissionComponents, 1));

        return implode('.', $permissionComponents);
    }
}
