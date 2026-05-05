<?php

/*
 * author Louis Perez
 * created on 28-04-2026-09h-21m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\Dispatching\DeliveryNote\Return;

use App\Enums\EnumHelperTrait;

enum ReturnDeliveryNoteStateEnum: string
{
    use EnumHelperTrait;


    case RECEIVED = 'received';
    case RETURNING = 'returning';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';


    public static function labels(): array
    {
        return [
            'received'           => __('Received'),
            'returning'         => __('Returning to locations'),
            'returned'         => __('Returned'),
            'cancelled'        => __('Cancelled')
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'queued'           => [
                'tooltip' => __('Waiting to Receive'),
                'icon'    => 'fal fa-clock',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'clock',
                    'type' => 'font-awesome-5'
                ]
            ],
            'unassigned'       => [
                'tooltip' => __('Unassigned'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-gray-500',  // Color for normal icon (Aiku)
                'color'   => 'gray',  // Color for box (Retina)
                'app'     => [
                    'name' => 'chair',
                    'type' => 'font-awesome-5'
                ]
            ],
            'handling'         => [
                'tooltip' => __('Handling'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'received' => [
                'tooltip' => __('Received'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
            'cancelled'  => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ]

        ];
    }
}
