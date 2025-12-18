<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Dec 2025 12:20:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\WaitingItem;

use App\Enums\EnumHelperTrait;

enum WaitingItemTypeEnum: string
{
    use EnumHelperTrait;


    case PRODUCTION = 'production';
    case RESTOCK = 'restock';
    case CUSTOMER = 'customer';


    public static function labels(): array
    {
        return [
            'production'       => __('Production'),
            'restock'          => __('Restock'),
            'customer'         => __('Customer'),

        ];
    }

    public static function stateIcon(): array
    {
        return [
            'production'       => [
                'tooltip' => __('Production'),
                'icon'    => 'fal fa-industry',

            ],
           'restock'          => [
               'tooltip' => __('Restock'),
               'icon'    => 'fal fa-warehouse',
           ],
            'customer'         => [
                'tooltip' => __('Customer'),
                'icon'    => 'fal fa-user-friends',
            ]


        ];
    }
}
