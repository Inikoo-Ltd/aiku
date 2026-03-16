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

    public static function labels(): array
    {
        return [
            'A' => __('Top Performer'),
            'B' => __('Good'),
            'C' => __('Average'),
            'D' => __('Zombie'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'A' => [
                'tooltip' => __('Top Performer | Covers the top 0–15% of revenue in last 90 days'),
                'icon' => 'fal fa-star',
                'class' => 'text-green-500',
                'color' => 'green',
                'app' => [
                    'name' => 'star',
                    'type' => 'font-awesome-5',
                ],
            ],
            'B' => [
                'tooltip' => __('Good | Covers 15–50% of revenue in last 90 days'),
                'icon' => 'fal fa-arrow-up',
                'class' => 'text-blue-500',
                'color' => 'blue',
                'app' => [
                    'name' => 'arrow-up',
                    'type' => 'font-awesome-5',
                ],
            ],
            'C' => [
                'tooltip' => __('Average | Covers 50–100% of revenue in last 90 days'),
                'icon' => 'fal fa-minus',
                'class' => 'text-yellow-500',
                'color' => 'yellow',
                'app' => [
                    'name' => 'minus',
                    'type' => 'font-awesome-5',
                ],
            ],
            'D' => [
                'tooltip' => __('Zombie | No sales in the last quarter'),
                'icon' => 'fal fa-user-alien',
                'class' => 'text-red-500',
                'color' => 'red',
                'app' => [
                    'name' => 'user-alien',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
