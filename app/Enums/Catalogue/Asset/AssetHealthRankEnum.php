<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 11 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Catalogue\Asset;

use App\Enums\EnumHelperTrait;

enum AssetHealthRankEnum: string
{
    use EnumHelperTrait;

    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    public static function labels(): array
    {
        return [
            'A' => __('Top Performer'),
            'B' => __('Good'),
            'C' => __('Average'),
            'D' => __('Inactive'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'A' => [
                'tooltip' => __('Top Performer'),
                'icon'    => 'fal fa-star',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'star',
                    'type' => 'font-awesome-5'
                ]
            ],
            'B' => [
                'tooltip' => __('Good'),
                'icon'    => 'fal fa-arrow-up',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
                'app'     => [
                    'name' => 'arrow-up',
                    'type' => 'font-awesome-5'
                ]
            ],
            'C' => [
                'tooltip' => __('Average'),
                'icon'    => 'fal fa-minus',
                'class'   => 'text-yellow-500',
                'color'   => 'yellow',
                'app'     => [
                    'name' => 'minus',
                    'type' => 'font-awesome-5'
                ]
            ],
            'D' => [
                'tooltip' => __('Inactive'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times-circle',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
