<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Mar 2023 13:45:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\DeliveryNoteItem;

use App\Enums\EnumHelperTrait;

enum DeliveryNoteItemStateEnum: string
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
    case OUT_OF_STOCK = 'out_of_stock'; // No dispatched because Out of stock
    case NO_DISPATCHED = 'no_dispatched'; // Other not dispatched reasons

    public static function labels(): array
    {
        return [
            'unassigned'       => __('Unassigned'),
            'queued'           => __('In Queue'),
            'handling'         => __('Handling'),
            'handling_blocked' => __('Handling Blocked'),
            'packed'           => __('Packed'),
            'finalised'        => __('Finalised'),
            'dispatched'       => __('Dispatched'),
            'cancelled'        => __('Cancelled'),
            'out_of_stock'     => __('Out of Stock'),
            'no_dispatched'    => __('No Dispatched')
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'unassigned'       => [
                'tooltip' => __('Unassigned'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-grey-500',  // Color for normal icon (Aiku)
                'color'   => 'grey',  // Color for box (Retina)
                'app'     => [
                    'name' => 'chair',
                    'type' => 'font-awesome-5'
                ]
            ],
            'queued'           => [
                'tooltip' => __('In Queue'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-lime-500',
                'color'   => 'lime',
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
            'handling_blocked' => [
                'tooltip' => __('Handling Blocked'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'packed'           => [
                'tooltip' => __('Packed'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'finalised'        => [
                'tooltip' => __('Finalised'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],

            'dispatched' => [
                'tooltip' => __('Dispatched'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
            'cancelled'  => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'out_of_stock'     => [
                'tooltip' => __('Out of Stock'),
                'icon'    => 'fal fa-empty-set',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'empty-set',
                    'type' => 'font-awesome-5'
                ]
            ],
            'no_dispatched'    => [
                'tooltip' => __('No Dispatched'),
                'icon'    => 'fal fa-exclamation-triangle',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'exclamation-triangle',
                    'type' => 'font-awesome-5'
                ]
            ]

        ];
    }

}
