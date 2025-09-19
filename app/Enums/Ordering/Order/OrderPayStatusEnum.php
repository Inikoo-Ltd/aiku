<?php

/*
 * author Arya Permana - Kirin
 * created on 14-03-2025-15h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Ordering\Order;

use App\Enums\EnumHelperTrait;

enum OrderPayStatusEnum: string
{
    use EnumHelperTrait;

    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case UNKNOWN = 'unknown';
    case NO_NEED = 'no_need';

    public static function labels(): array
    {
        return [
            'unpaid' => __('Unpaid'),
            'paid'   => __('Paid'),
            'unknown' => __('Unknown payment status'),
            'no_need' => __('No need to pay'),
        ];
    }

    public static function typeIcon(): array
    {
        return [
            'unpaid' => [
                'tooltip' => __('Unpaid'),
                'icon'    => 'fal fa-circle',
                'class'   => 'text-gray-500',  // Color for normal icon (Aiku)
                'color'   => 'gray',  // Color for box (Retina)
                'app'     => [
                    'name' => 'circle',
                    'type' => 'font-awesome-5'
                ]
            ],
            'paid'   => [
                'tooltip' => __('Paid'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-600',  // Color for normal icon (Aiku)
                'color'   => 'lime',  // Color for box (Retina)
                'app'     => [
                    'name' => 'check-circle',
                    'type' => 'font-awesome-5'
                ]
            ],
            'unknown' => [
                'tooltip' => __('Unknown'),
                'icon'    => 'fal fa-question-circle',
                'class'   => 'text-gray-500',
                'color'   => 'question-circle',
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'no_need' => [
                'tooltip' => __('No need to pay'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-600',
                'color'   => 'lime',
                'app'     => [
                    'name' => 'check-circle',
                ]
            ]
        ];
    }
}
