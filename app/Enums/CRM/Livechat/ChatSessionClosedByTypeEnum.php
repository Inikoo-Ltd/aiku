<?php

namespace App\Enums\CRM\Livechat;
use App\Enums\EnumHelperTrait;
enum ChatSessionClosedByTypeEnum: string
{
    use EnumHelperTrait;

    case USER   = 'user';
    case AGENT  = 'agent';
    case SYSTEM = 'system';

    public static function labels(): array
    {
        return [
            'user'   => __('User'),
            'agent'  => __('Agent'),
            'system' => __('System'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'user' => [
                'tooltip' => __('Closed by User'),
                'icon'    => 'fas fa-user',
                'class'   => 'text-green-500',
                'color'   => 'green'
            ],
            'agent' => [
                'tooltip' => __('Closed by Agent'),
                'icon'    => 'fas fa-headset',
                'class'   => 'text-blue-500',
                'color'   => 'blue'
            ],
            'system' => [
                'tooltip' => __('Closed by System'),
                'icon'    => 'fas fa-robot',
                'class'   => 'text-gray-500',
                'color'   => 'gray'
            ],
        ];
    }

    public static function count(): array
    {
        // TODO: Implement count logic based on your stats
        return [
            'user'   => 0,
            'agent'  => 0,
            'system' => 0,
        ];
    }
}
