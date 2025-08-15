<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\Inventory\Trolley;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum TrolleyStateEnum: string
{
    use EnumHelperTrait;

    case CURRENT  = 'current';
    case HISTORIC = 'historic';

    public static function labels(): array
    {
        return [
            'current'  => __('Current'),
            'historic' => __('Historic'),
        ];
    }

    public static function count(Organisation|Group $parent): array
    {
        $stats = $parent->inventoryStats;

        return [
            'current'  => $stats->number_trolleys_state_current,
            'historic' => $stats->number_trolleys_state_historic,
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'current' => [
                'tooltip' => __('Current'),
                'icon'    => 'fal fa-shopping-cart',
                'class'   => 'text-blue-500'
            ],
            'historic' => [
                'tooltip' => __('Historic'),
                'icon'    => 'fal fa-archive',
                'class'   => 'text-gray-500'
            ],
        ];
    }
}
