<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Catalogue;

use App\Enums\EnumHelperTrait;

enum HealthRankEnum: string
{
    use EnumHelperTrait;

    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case Z = 'Z';

    public static function labels(): array
    {
        return [
            'A' => __('Top Performer'),
            'B' => __('Good'),
            'C' => __('Average'),
            'D' => __('Dead'),
            'Z' => __('Zombie'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'A' => [
                'tooltip' => __('Top Performer | Covers the top 0–15% of revenue in last 90 days'),
                'text'    => 'A',
                'class'   => 'text-green-500',
                'color'   => 'green',
            ],
            'B' => [
                'tooltip' => __('Good | Covers 15–50% of revenue in last 90 days'),
                'text'    => 'B',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
            ],
            'C' => [
                'tooltip' => __('Average | Covers 50–100% of revenue in last 90 days'),
                'text'    => 'C',
                'class'   => 'text-yellow-500',
                'color'   => 'yellow',
            ],
            'D' => [
                'tooltip' => __('Dead | No sales and no stock in the last quarter'),
                'icon'    => 'fal fa-tombstone',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'tombstone',
                    'type' => 'font-awesome-5',
                ],
            ],
            'Z' => [
                'tooltip' => __('Zombie | No sales but has stock in the last quarter'),
                'svg'     => 'zombie',
                'class'   => 'w-5 h-5 mx-auto',
                'color'   => 'orange',
            ],
        ];
    }
}
