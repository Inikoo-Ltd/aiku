<?php

namespace App\Enums\Production\JobOrder;

use App\Enums\EnumHelperTrait;

// pls review

enum JobOrderStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case RECEIVED = 'received';
    case NOT_RECEIVED = 'not_received';
    case BOOKING_IN = 'booking_in';
    case BOOKED_IN = 'booked_in';

    public static function labels($forElements = false): array
    {
        return [
            'in_process' => __('In Process'),
            'submitted' => __('Submitted'),
            'confirmed' => __('Confirmed'),
            'received' => __('Received'),
            'not_received' => __('Not Received'),
            'booking_in' => __('Booking In'),
            'booked_in' => __('Booked In'),
        ];
    }

    public static function stateIcon(): array
    {
        // Icon is imported in resources/js/Composables/Icon/PalletDeliveryStateEnum.ts
        return [
            'in_process' => [
                'tooltip' => __('In process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-lime-500',  // Color for normal icon (Aiku)
                'color' => 'lime',  // Color for box (Retina)
                'app' => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'submitted' => [
                'tooltip' => __('Submitted'),
                'icon' => 'fal fa-share',
                'class' => 'text-indigo-400',
                'color' => 'indigo',
                'app' => [
                    'name' => 'share',
                    'type' => 'font-awesome-5',
                ],
            ],
            'confirmed' => [
                'tooltip' => __('Confirmed'),
                'icon' => 'fal fa-spell-check',
                'class' => 'text-emerald-500',
                'color' => 'emerald',
                'app' => [
                    'name' => 'spell-check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'received' => [
                'tooltip' => __('Received'),
                'icon' => 'fal fa-check',
                'class' => 'text-slate-500',
                'color' => 'slate',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'not_received' => [
                'tooltip' => __('Not Received'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => 'slate',
                'app' => [
                    'name' => 'times',
                    'type' => 'font-awesome-5',
                ],
            ],
            'booking_in' => [
                'tooltip' => __('Booking in'),
                'icon' => 'fal fa-check',
                'class' => 'text-purple-500',
                'color' => 'purple',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'booked_in' => [
                'tooltip' => __('Booked in'),
                'icon' => 'fal fa-check-double',
                'class' => 'text-purple-500',
                'color' => 'purple',
                'app' => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
