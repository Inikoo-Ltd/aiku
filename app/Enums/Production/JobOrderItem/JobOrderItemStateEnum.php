<?php

namespace App\Enums\Production\JobOrderItem;

use App\Enums\EnumHelperTrait;

enum JobOrderItemStateEnum: string
{
    use EnumHelperTrait;

    // Status: receiving
    case IN_PROCESS = 'in_process';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case RECEIVED = 'received';
    case BOOKING_IN = 'booking_in';
    case BOOKED_IN = 'booked_in';

    // Status: not_received
    case NOT_RECEIVED = 'not_received';

    // Status: storing
    case STORING = 'storing';

    // Status: returning
    case PICKING = 'picking';
    case PICKED = 'picked';

    // Status: incident
    case DAMAGED = 'damaged';
    case LOST = 'lost';

    // Status: returned
    case DISPATCHED = 'dispatched';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'submitted' => __('Submitted'),
            'confirmed' => __('Confirmed'),
            'not_received' => __('Not Received'),
            'received' => __('Received'),
            'booking_in' => __('Booking in'),
            'booked_in' => __('Booked in'),
            'storing' => __('Storing'),
            'picking' => __('Picking'),
            'picked' => __('Picked'),
            'dispatched' => __('Dispatched'),
            'lost' => __('Lost'),
            'damaged' => __('Damaged'),
        ];
    }

    public static function stateIcon(): array
    {
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
                'tooltip' => __('not received'),
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
                'color' => 'red',
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
                'class' => 'text-purple-300',
                'color' => 'purple',
                'app' => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5',
                ],
            ],
            'storing' => [
                'tooltip' => __('Storing'),
                'icon' => 'fal fa-check-double',
                'class' => 'text-purple-500',
                'color' => 'purple',
                'app' => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5',
                ],
            ],

            'dispatched' => [
                'tooltip' => __('Dispatched'),
                'icon' => 'fal fa-sign-out-alt',
                'class' => 'text-gray-400',
                'color' => 'gray',
                'app' => [
                    'name' => 'sign-out-alt',
                    'type' => 'font-awesome-5',
                ],
            ],
            'picking' => [
                'tooltip' => __('Picking'),
                'icon' => 'fal fa-check',
                'class' => 'text-green-400',
                'color' => 'green',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'picked' => [
                'tooltip' => __('Picked'),
                'icon' => 'fal fa-check',
                'class' => 'text-green-400',
                'color' => 'green',
                'app' => [
                    'name' => 'check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'damaged' => [
                'tooltip' => __('Damaged'),
                'icon' => 'fal fa-fragile',
                'class' => 'text-red-400',
                'color' => 'red',
                'app' => [
                    'name' => 'glass-fragile',
                    'type' => 'material-community',
                ],
            ],
            'lost' => [
                'tooltip' => __('Not Picked'),
                'icon' => 'fal fa-ghost',
                'class' => 'text-red-400',
                'color' => 'red',
                'app' => [
                    'name' => 'ghost',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
