<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 04:05:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Product;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ProductTradeConfigEnum: string
{
    use EnumHelperTrait;

    //enum('Online Force Out of Stock','Online Auto','Offline','Online Force For Sale')

    case AUTO = 'auto';
    case FORCE_OFFLINE = 'force-offline';
    case FORCE_OUT_OF_STOCK = 'force-out-of-stock';
    case FORCE_FOR_SALE = 'force-for-sale';


    public static function labels(): array
    {
        return [
            'auto' => __('Auto'),
            'force-offline' => __('Force Offline'),
            'force-out-of-stock' => __('Force Out of Stock'),
            'force-for-sale' => __('Force For Sale')
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'auto' => [
                'tooltip' => __('Auto'),
                'icon'    => 'fal fa-sync',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
                'app'     => [
                    'name' => 'sync',
                    'type' => 'font-awesome-5'
                ]
            ],
            'force-offline' => [
                'tooltip' => __('Force Offline'),
                'icon'    => 'fal fa-ban',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'ban',
                    'type' => 'font-awesome-5'
                ]
            ],
            'force-out-of-stock' => [
                'tooltip' => __('Force Out of Stock'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'force-for-sale' => [
                'tooltip' => __('Force For Sale'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-emerald-500',
                'color'   => 'emerald',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ]
        ];
    }

    public static function count(Group|Shop|ProductCategory|Organisation|Collection|ShopifyUser $parent): array
    {
        if ($parent instanceof ShopifyUser) {
            return [];
        }

        $stats = $parent->stats;

        return [
            'auto' => $stats->number_products_trade_config_auto,
            'force-offline' => $stats->number_products_trade_config_force_offline,
            'force-out-of-stock' => $stats->number_products_trade_config_force_out_of_stock,
            'force-for-sale' => $stats->number_products_trade_config_force_for_sale

        ];
    }


}
