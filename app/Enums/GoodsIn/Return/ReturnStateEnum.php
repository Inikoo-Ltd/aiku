<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Return state enum with waiting_to_receive as initial state
 */

namespace App\Enums\GoodsIn\Return;

use App\Enums\EnumHelperTrait;

enum ReturnStateEnum: string
{
    use EnumHelperTrait;

    case WAITING_TO_RECEIVE = 'waiting_to_receive';  // Initial state
    case RECEIVED = 'received';
    case INSPECTED = 'inspected';
    case RESTOKED = 'restocked';
    case CANCELLED = 'cancelled';

    public static function labels(): array
    {
        return [
            'waiting_to_receive' => __('Waiting to Receive'),
            'received'           => __('Received'),
            'inspected'          => __('Inspected'),
            'restocked'          => __('Restocked'),
            'cancelled'          => __('Cancelled'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'waiting_to_receive' => [
                'tooltip' => __('Waiting to Receive'),
                'icon'    => 'fal fa-clock',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'clock',
                    'type' => 'font-awesome-5'
                ]
            ],
            'received'           => [
                'tooltip' => __('Received'),
                'icon'    => 'fal fa-inbox-in',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
                'app'     => [
                    'name' => 'inbox-in',
                    'type' => 'font-awesome-5'
                ]
            ],
            'inspected'          => [
                'tooltip' => __('Inspected'),
                'icon'    => 'fal fa-search',
                'class'   => 'text-yellow-500',
                'color'   => 'yellow',
                'app'     => [
                    'name' => 'search',
                    'type' => 'font-awesome-5'
                ]
            ],
            'restocked'          => [
                'tooltip' => __('Restocked'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5'
                ]
            ],
            'cancelled'          => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
