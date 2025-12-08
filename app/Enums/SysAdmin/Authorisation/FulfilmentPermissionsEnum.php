<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jan 2024 20:08:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\Authorisation;

use App\Models\Fulfilment\Fulfilment;

enum FulfilmentPermissionsEnum: string
{
    case FULFILMENT_SHOP = 'fulfilment-shop';
    case FULFILMENT_SHOP_VIEW = 'fulfilment-shop.view';
    case FULFILMENT_SHOP_EDIT = 'fulfilment-shop.edit';
    case SUPERVISOR_FULFILMENT_SHOP = 'supervisor-fulfilment-shop';

    public static function getAllValues(Fulfilment $fulfilment): array
    {
        $rawPermissionsNames = array_column(FulfilmentPermissionsEnum::cases(), 'value');

        $permissionsNames = [];
        foreach ($rawPermissionsNames as $rawPermissionsName) {
            $permissionsNames[] = self::getPermissionName($rawPermissionsName, $fulfilment);
        }

        return $permissionsNames;
    }

    public static function getPermissionName(string $rawName, Fulfilment $fulfilment): string
    {
        $permissionComponents = explode('.', $rawName);
        $permissionComponents = array_merge(array_slice($permissionComponents, 0, 1), [$fulfilment->id], array_slice($permissionComponents, 1));

        return implode('.', $permissionComponents);
    }
}
