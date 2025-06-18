<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 00:47:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Shop;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ShopStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS   = 'in_process';
    case OPEN         = 'open';
    case CLOSING_DOWN = 'closing_down';
    case CLOSED       = 'closed';

    public static function labels(): array
    {
        return [
            'in_process'      => __('In Process'),
            'open'            => __('Open'),
            'closing_down'    => __('Closing Down'),
            'closed'          => __('Closed')
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'      => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',
                'color'   => 'lime',
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'open'            => [
                'tooltip' => __('Open'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-emerald-500',
                'color'   => 'emerald',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'closing_down'    => [
                'tooltip' => __('Closing Down'),
                'icon'    => 'fal fa-hourglass-half',
                'class'   => 'text-yellow-500',
                'color'   => 'yellow',
                'app'     => [
                    'name' => 'hourglass-half',
                    'type' => 'font-awesome-5'
                ]
            ],
            'closed'          => [
                'tooltip' => __('Closed'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times-circle',
                    'type' => 'font-awesome-5'
                ]
            ]
        ];
    }

    public static function count(Organisation|Group $parent): array
    {
        $stats = $parent->catalogueStats;

        return [
            'in_process'      => $stats->number_shops_state_in_process,
            'open'            => $stats->number_shops_state_open,
            'closing_down'    => $stats->number_shops_state_closing_down,
            'closed'          => $stats->number_shops_state_closed
        ];
    }
}
