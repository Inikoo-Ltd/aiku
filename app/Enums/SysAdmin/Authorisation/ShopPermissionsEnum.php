<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jan 2024 20:08:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\Authorisation;

use App\Models\Market\Shop;

enum ShopPermissionsEnum: string
{
    case SHOP = 'shop';

    case SHOP_EDIT = 'shop.edit';

    case SHOP_VIEW = 'shop.view';

    case CRM      = 'crm';
    case CRM_EDIT = 'crm.edit';
    case CRM_VIEW = 'crm.view';





    case WEBSITE      = 'website';
    case WEBSITE_EDIT = 'website.edit';
    case WEBSITE_VIEW = 'website.view';

    case ORDERS      = 'orders';
    case ORDERS_EDIT = 'orders.edit';
    case ORDERS_VIEW = 'orders.view';

    case MARKETING      = 'marketing';
    case MARKETING_EDIT = 'marketing.edit';
    case MARKETING_VIEW = 'marketing.view';



    case SUPERVISOR_SHOPS       = 'supervisor-shops';
    case SUPERVISOR_CRM         = 'supervisor-crm';
    case SUPERVISOR_WEBSITE     = 'supervisor-website';

    case SUPERVISOR_ORDERS = 'supervisor-orders';

    case SUPERVISOR_MARKETING = 'supervisor-marketing';

    public static function getAllValues(Shop $shop): array
    {

        $rawPermissionsNames = array_column(ShopPermissionsEnum::cases(), 'value');

        $permissionsNames    = [];
        foreach ($rawPermissionsNames as $rawPermissionsName) {
            $permissionsNames[] = self::getPermissionName($rawPermissionsName, $shop);
        }

        return $permissionsNames;
    }

    public static function getPermissionName(string $rawName, Shop $shop): string
    {
        $permissionComponents = explode('.', $rawName);
        $permissionComponents = array_merge(array_slice($permissionComponents, 0, 1), [$shop->slug], array_slice($permissionComponents, 1));

        return join('.', $permissionComponents);
    }

}
