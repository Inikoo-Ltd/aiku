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

    case PRODUCTS      = 'products';
    case PRODUCTS_EDIT = 'products.edit';
    case PRODUCTS_VIEW = 'products.view';

    case CRM      = 'crm';
    case CRM_EDIT = 'crm.edit';
    case CRM_VIEW = 'crm.view';

    /*
     * Chat is its own permission rather than a part of CRM: shop admins and the technical
     * team hold CRM for reasons that have nothing to do with answering customers, and
     * working somebody's live conversations should be granted deliberately.
     */
    case CHAT      = 'chat';
    case CHAT_VIEW = 'chat.view';

    /*
     * Supervising chat is not the same as working it: a manager takes over, writes and
     * closes any conversation on the shop, but is never in the routing pool and never
     * counts as an agent.
     */
    case CHAT_MANAGER = 'chat-m';

    case CRM_PROSPECTS      = 'crm.prospects';
    case CRM_PROSPECTS_EDIT = 'crm.prospects.edit';
    case CRM_PROSPECTS_VIEW = 'crm.prospects.view';

    case WEB      = 'web';
    case WEB_EDIT = 'web.edit';
    case WEB_VIEW = 'web.view';
    case WEB_EDIT_LANDING_PAGES = 'web.edit.landing-pages';

    case ORDERS      = 'orders';
    case ORDERS_EDIT = 'orders.edit';
    case ORDERS_VIEW = 'orders.view';

    case DISCOUNTS      = 'discounts';
    case DISCOUNTS_EDIT = 'discounts.edit';
    case DISCOUNTS_VIEW = 'discounts.view';

    case MARKETING      = 'marketing';
    case MARKETING_EDIT = 'marketing.edit';
    case MARKETING_VIEW = 'marketing.view';

    case SUPERVISOR_PRODUCTS     = 'supervisor-products';
    case SUPERVISOR_CRM          = 'supervisor-crm';
    case SUPERVISOR_WEB          = 'supervisor-web';
    case SUPERVISOR_ORDERS       = 'supervisor-orders';
    case SUPERVISOR_DISCOUNTS    = 'supervisor-discounts';
    case SUPERVISOR_MARKETING    = 'supervisor-marketing';

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

        return join('.', $permissionComponents);
    }
}
