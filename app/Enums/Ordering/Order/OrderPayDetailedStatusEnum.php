<?php

/*
 * author Arya Permana - Kirin
 * created on 14-03-2025-15h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Ordering\Order;

use App\Enums\EnumHelperTrait;

enum OrderPayDetailedStatusEnum: string
{
    use EnumHelperTrait;

    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case OVERPAID = 'overpaid';
    case UNKNOWN = 'unknown';

    public static function labels(): array
    {
        return [
            'unpaid' => __('Unpaid'),
            'paid'   => __('Paid'),
            'unknown' => __('Unknown payment status'),
            'partially_paid' => __('Partially Paid'),
            'overpaid' => __('Overpaid'),
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
            'partially_paid' => [
                'tooltip' => __('Partially Paid'),
                'icon'    => 'fal fa-adjust',
                'class'   => 'text-amber-500',  // Color for normal icon (Aiku)
                'color'   => 'amber',  // Color for box (Retina)
                'app'     => [
                    'name' => 'adjust',
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
            'overpaid' => [
                'tooltip' => __('Overpaid'),
                'icon'    => 'fal fa-plus-circle',
                'class'   => 'text-blue-600',  // Color for normal icon (Aiku)
                'color'   => 'blue',  // Color for box (Retina)
                'app'     => [
                    'name' => 'plus-circle',
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
        ];
    }
}
