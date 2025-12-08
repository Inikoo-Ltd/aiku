<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jan 2024 20:08:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\Authorisation;

use App\Models\Catalogue\Shop;

enum ShopPermissionsEnum: string
{
    case SHOP_ADMIN = 'shop-admin';

    case PRODUCTS = 'products';
    case PRODUCTS_EDIT = 'products.edit';
    case PRODUCTS_VIEW = 'products.view';

    case CRM = 'crm';
    case CRM_EDIT = 'crm.edit';
    case CRM_VIEW = 'crm.view';

    case CRM_PROSPECTS = 'crm.prospects';
    case CRM_PROSPECTS_EDIT = 'crm.prospects.edit';
    case CRM_PROSPECTS_VIEW = 'crm.prospects.view';

    case WEB = 'web';
    case WEB_EDIT = 'web.edit';
    case WEB_VIEW = 'web.view';

    case ORDERS = 'orders';
    case ORDERS_EDIT = 'orders.edit';
    case ORDERS_VIEW = 'orders.view';

    case DISCOUNTS = 'discounts';
    case DISCOUNTS_EDIT = 'discounts.edit';
    case DISCOUNTS_VIEW = 'discounts.view';

    case MARKETING = 'marketing';
    case MARKETING_EDIT = 'marketing.edit';
    case MARKETING_VIEW = 'marketing.view';

    case SUPERVISOR_PRODUCTS = 'supervisor-products';
    case SUPERVISOR_CRM = 'supervisor-crm';
    case SUPERVISOR_WEB = 'supervisor-web';
    case SUPERVISOR_ORDERS = 'supervisor-orders';
    case SUPERVISOR_DISCOUNTS = 'supervisor-discounts';
    case SUPERVISOR_MARKETING = 'supervisor-marketing';

    public static function getAllValues(Shop $shop): array
    {
        $rawPermissionsNames = array_column(ShopPermissionsEnum::cases(), 'value');

        $permissionsNames = [];
        foreach ($rawPermissionsNames as $rawPermissionsName) {
            $permissionsNames[] = self::getPermissionName($rawPermissionsName, $shop);
        }

        return $permissionsNames;
    }

    public static function getPermissionName(string $rawName, Shop $shop): string
    {
        $permissionComponents = explode('.', $rawName);
        $permissionComponents = array_merge(array_slice($permissionComponents, 0, 1), [$shop->id], array_slice($permissionComponents, 1));

        return implode('.', $permissionComponents);
    }
}
