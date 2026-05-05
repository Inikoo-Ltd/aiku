<?php

/*
 * author Louis Perez
 * created on 28-04-2026-09h-21m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\GoodsIn\ReturnDeliveryNote;

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
            'returning'         => __('Returning to Locations'),
            'returned'         => __('Returned'),
            'cancelled'        => __('Cancelled')
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'received'           => [
                'tooltip' => __('Received'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-gray-500', 
                'color'   => 'gray',
                'app'     => [
                    'name' => 'chair',
                    'type' => 'font-awesome-5'
                ]
            ],
            'returning'         => [
                'tooltip' => __('Returning to Locations'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'returned' => [
                'tooltip' => __('Returned'),
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
