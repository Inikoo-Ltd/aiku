<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 02:25:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Masters\MasterAsset;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;

enum MasterAssetTypeEnum: string
{
    use EnumHelperTrait;

    case PRODUCT       = 'product';
    case SERVICE       = 'service';
    case SUBSCRIPTION  = 'subscription';
    case RENTAL        = 'rental';
    case CHARGE        = 'charge';
    case SHIPPING_ZONE = 'shipping_zone';


    public static function labels(): array
    {
        return [
            'product'       => __('Product'),
            'service'       => __('Services'),
            'subscription'  => __('Subscriptions'),
            'rental'        => __('Rentals'),
            'charge'        => __('Charges'),
            'shipping_zone' => __('Shipping zones'),
        ];
    }


    public static function typeIcon(): array
    {
        return [
            'product'       => [
                'tooltip' => __('Physical good'),
                'icon'    => 'fal fa-cube',
                'app'     => [
                    'name' => 'cube',
                    'type' => 'font-awesome-5'
                ]
            ],
            'subscription'  => [
                'tooltip' => __('Subscription'),
                'icon'    => 'fal fa-bell',
                'app'     => [
                    'name' => 'bell',
                    'type' => 'font-awesome-5'
                ]
            ],
            'service'       => [
                'tooltip' => __('Service'),
                'icon'    => 'fal fa-concierge-bell',
                'app'     => [
                    'name' => 'concierge-bell',
                    'type' => 'font-awesome-5'
                ]
            ],
            'rental'        => [
                'tooltip' => __('Rental'),
                'icon'    => 'fal fa-garage',
                'app'     => [
                    'name' => 'garage',
                    'type' => 'font-awesome-5'
                ]
            ],
            'shipping_zone' => [
                'tooltip' => __('Shipping zone'),
                'icon'    => 'fal fa-shipping-fast',
                'app'     => [
                    'name' => 'shipping-fast',
                    'type' => 'font-awesome-5'
                ]
            ],
            'charge'        => [
                'tooltip' => __('Charges'),
                'icon'    => 'fal fa-charging-station',
                'app'     => [
                    'name' => 'shipping-fast',
                    'type' => 'font-awesome-5'
                ]
            ],

        ];
    }

    public static function count(Group $group): array
    {

        $stats = $group->goodsStats;

        return[
            'product'       => $stats->number_master_assets_type_product,
            'subscription'  => $stats->number_master_assets_type_subscription,
            'service'       => $stats->number_master_assets_type_service,
            'rental'        => $stats->number_master_assets_type_rental,
            'charge'        => $stats->number_master_assets_type_charge,
            'shipping_zone' => $stats->number_master_assets_type_shipping_zone,
        ];
    }

}
