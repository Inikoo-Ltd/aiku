<?php

/*
 * author Louis Perez
 * created on 28-04-2026-09h-21m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\GoodsIn\ReturnDeliveryNote;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ReturnDeliveryNoteStateEnum: string
{
    use EnumHelperTrait;


    case RECEIVED = 'received';
    case RETURNING = 'returning';
    case RETURNED = 'returned';
    case DONE = 'done';
    case CANCELLED = 'cancelled';


    public static function labels(): array
    {
        return [
            'received'  => __('Received'),
            'returning' => __('Returning to Locations'),
            'returned'  => __('Returned'),
            'cancelled' => __('Cancelled'),
            'done'      => __('Done')
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'received'  => [
                'tooltip' => __('Received'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'chair',
                    'type' => 'font-awesome-5'
                ]
            ],
            'returning' => [
                'tooltip' => __('Returning to Locations'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'returned'  => [
                'tooltip' => __('Returned'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
            'done'  => [
                'tooltip' => __('Done'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
            'cancelled' => [
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

    public static function count(Group|Organisation|Shop|Warehouse $parent): array
    {
        if ($parent instanceof Organisation || $parent instanceof Group) {
            $stats = $parent->procurementStats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'received'         => $stats->number_return_delivery_notes_state_received,
            'returning'        => $stats->number_return_delivery_notes_state_returning,
            'returned'         => $stats->number_return_delivery_notes_state_returned,
            'done'             => $stats->number_return_delivery_notes_state_done,
            'cancelled'        => $stats->number_return_delivery_notes_state_cancelled,
        ];
    }
}
