<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 15:10:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\Catalogue\Packaging;

use App\Enums\EnumHelperTrait;

enum PackagingStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'in_process'   => __('In Process'),
            'active'       => __('Active'),
            'discontinued' => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'   => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',
                'color'   => 'lime',
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'active'       => [
                'tooltip' => __('Active'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'discontinued' => [
                'tooltip' => __('Discontinued'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
