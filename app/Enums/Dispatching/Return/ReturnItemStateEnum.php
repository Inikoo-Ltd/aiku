<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Return item state enum
 */

namespace App\Enums\Dispatching\Return;

use App\Enums\EnumHelperTrait;

enum ReturnItemStateEnum: string
{
    use EnumHelperTrait;

    case PENDING = 'pending';
    case RECEIVED = 'received';
    case INSPECTING = 'inspecting';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case RESTOCKED = 'restocked';

    public static function labels(): array
    {
        return [
            'pending'    => __('Pending'),
            'received'   => __('Received'),
            'inspecting' => __('Inspecting'),
            'accepted'   => __('Accepted'),
            'rejected'   => __('Rejected'),
            'restocked'  => __('Restocked'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'pending' => [
                'tooltip' => __('Pending'),
                'icon'    => 'fal fa-clock',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'clock',
                    'type' => 'font-awesome-5'
                ]
            ],
            'received' => [
                'tooltip' => __('Received'),
                'icon'    => 'fal fa-inbox-in',
                'class'   => 'text-blue-500',
                'color'   => 'blue',
                'app'     => [
                    'name' => 'inbox-in',
                    'type' => 'font-awesome-5'
                ]
            ],
            'inspecting' => [
                'tooltip' => __('Inspecting'),
                'icon'    => 'fal fa-search',
                'class'   => 'text-yellow-500',
                'color'   => 'yellow',
                'app'     => [
                    'name' => 'search',
                    'type' => 'font-awesome-5'
                ]
            ],
            'accepted' => [
                'tooltip' => __('Accepted'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'rejected' => [
                'tooltip' => __('Rejected'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            'restocked' => [
                'tooltip' => __('Restocked'),
                'icon'    => 'fal fa-box-check',
                'class'   => 'text-indigo-500',
                'color'   => 'indigo',
                'app'     => [
                    'name' => 'box-check',
                    'type' => 'font-awesome-5'
                ]
            ],
        ];
    }
}
