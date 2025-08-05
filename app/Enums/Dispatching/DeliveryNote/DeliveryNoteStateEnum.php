<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 05:07:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\DeliveryNote;

use App\Enums\EnumHelperTrait;

enum DeliveryNoteStateEnum: string
{
    use EnumHelperTrait;


    case UNASSIGNED = 'unassigned';
    case QUEUED = 'queued'; // picker assigned
    case HANDLING = 'handling'; // picking and packing
    case HANDLING_BLOCKED = 'handling_blocked';
    case PACKED = 'packed';
    case FINALISED = 'finalised';
    case DISPATCHED = 'dispatched';
    case CANCELLED = 'cancelled';


    public static function labels(): array
    {
        return [
            'unassigned' => __('Unassigned'),
            'queued'     => __('In Queue'),
            'handling'   => __('Handling'),
            'handling_blocked' => __('Handling Blocked'),
            'packed'     => __('Packed'),
            'finalised'  => __('Finalised'),
            'dispatched' => __('Dispatched'),
            'cancelled'  => __('Cancelled')
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'unassigned' => [
                'tooltip' => __('Unassigned'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-gray-500',  // Color for normal icon (Aiku)
                'color'   => 'gray',  // Color for box (Retina)
                'app'     => [
                    'name' => 'chair',
                    'type' => 'font-awesome-5'
                ]
            ],
            'queued'     => [
                'tooltip' => __('In Queue'),
                'icon'    => 'fal fa-hourglass-start',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'chair',
                    'type' => 'font-awesome-5'
                ]
            ],
            'handling'   => [
                'tooltip' => __('Handling'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'handling_blocked' => [
                'tooltip' => __('Handling Blocked'),
                'icon'    => 'fal fa-allergies',
                'class'   => 'text-red-300',
                'color'   => 'red',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'packed'     => [
                'tooltip' => __('Packed'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'finalised'  => [
                'tooltip' => __('Finalised'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-indigo-500',
                'color'   => 'indigo',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],

            'dispatched' => [
                'tooltip' => __('Dispatched'),
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
