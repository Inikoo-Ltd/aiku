<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 15:10:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\Catalogue\Leaflet;

use App\Enums\EnumHelperTrait;

enum LeafletStateEnum: string
{
    use EnumHelperTrait;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public static function labels(): array
    {
        return [
            'active'   => __('Active'),
            'inactive' => __('Inactive'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'active'   => [
                'tooltip' => __('Active'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'inactive' => [
                'tooltip' => __('Inactive'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-gray-400',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
