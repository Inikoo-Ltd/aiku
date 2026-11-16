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
    case REFUNDED = 'refunded';
    case UNKNOWN = 'unknown';
    case TO_BE_PAID_BY_MARKETPLACE = 'to_be_paid_by_marketplace';
    case PAID_BY_MARKETPLACE = 'paid_by_marketplace';

    public static function labels(): array
    {
        return [
            'unpaid' => __('Unpaid'),
            'paid'   => __('Paid'),
            'unknown' => __('Unknown payment status'),
            'partially_paid' => __('Partially Paid'),
            'overpaid' => __('Overpaid'),
            'refunded' => __('Refunded'),
            'to_be_paid_by_marketplace' => __('To be paid by marketplace'),
            'paid_by_marketplace' => __('Paid by marketplace'),
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
            'refunded' => [
                'tooltip' => __('Refunded'),
                'icon'    => 'fal fa-undo-alt',
                'class'   => 'text-purple-500',  // Color for normal icon (Aiku)
                'color'   => 'purple',  // Color for box (Retina)
                'app'     => [
                    'name' => 'undo-alt',
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
            'to_be_paid_by_marketplace' => [
                'tooltip' => __('To be paid by marketplace'),
                'icon'    => 'fal fa-hourglass-half',
                'class'   => 'text-amber-500',
                'color'   => 'amber',
                'app'     => [
                    'name' => 'hourglass-half',
                    'type' => 'font-awesome-5'
                ]
            ],
            'paid_by_marketplace' => [
                'tooltip' => __('Paid by marketplace'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-600',
                'color'   => 'lime',
                'app'     => [
                    'name' => 'check-circle',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
