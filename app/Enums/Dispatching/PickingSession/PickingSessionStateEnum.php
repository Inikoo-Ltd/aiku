<?php

/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-18h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Dispatching\PickingSession;

use App\Enums\EnumHelperTrait;

enum PickingSessionStateEnum: string
{
    use EnumHelperTrait;


    case IN_PROCESS = 'in_process';
    case HANDLING = 'handling'; // picking and packing
    case HANDLING_BLOCKED = 'handling_blocked';
    case PICKING_FINISHED = 'picking_finished';
    case PACKING_FINISHED = 'packing_finished';

    public static function labels(): array
    {
        return [
            'in_process'       => __('In Process'),
            'handling'         => __('Handling'),
            'handling_blocked' => __('Handling Blocked'),
            'picking_finished' => __('Picking Finished'),
            'packing_finished' => __('Packing Finished'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'     => [
                'tooltip' => __('In Process'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-lime-500',
                'color'   => 'lime',
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
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'picking_finished'     => [
                'tooltip' => __('Picking Finished'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'packing_finished'     => [
                'tooltip' => __('Picking Finished'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],

        ];
    }
}
