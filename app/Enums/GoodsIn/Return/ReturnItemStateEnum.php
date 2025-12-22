<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Return item state enum
 */

namespace App\Enums\GoodsIn\Return;

use App\Enums\EnumHelperTrait;

enum ReturnItemStateEnum: string
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
            'waiting_to_receive' => __('Pending'),
            'received'           => __('Received'),
            'inspected'          => __('Inspected'),
            'restocked'          => __('Restocked'),
            'cancelled'          => __('Cancelled'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'waiting_to_receive'    => [
                'tooltip' => __('Pending'),
                'icon'    => 'fal fa-clock',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'clock',
                    'type' => 'font-awesome-5'
                ]
            ],
            'received'   => [
                'tooltip' => __('Received'),
                'icon'    => 'fal fa-inbox-in',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
                'app'     => [
                    'name' => 'inbox-in',
                    'type' => 'font-awesome-5'
                ]
            ],
            'inspected' => [
                'tooltip' => __('Inspected'),
                'icon'    => 'fal fa-search',
                'class'   => 'text-yellow-500',
                'color'   => 'yellow',
                'app'     => [
                    'name' => 'search',
                    'type' => 'font-awesome-5'
                ]
            ],
            'restocked'  => [
                'tooltip' => __('Restocked'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-indigo-500',
                'color'   => 'indigo',
                'app'     => [
                    'name' => 'box-check',
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
            ],
        ];
    }
}
