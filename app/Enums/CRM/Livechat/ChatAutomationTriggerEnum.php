<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatAutomationTriggerEnum: string
{
    use EnumHelperTrait;

    case WELCOME = 'welcome';
    case OFFLINE = 'offline';
    case WAITING = 'waiting';
    case NO_REPLY = 'no_reply';

    public static function labels(): array
    {
        return [
            'welcome'  => __('Welcome message'),
            'offline'  => __('Out of hours'),
            'waiting'  => __('Waiting for agent'),
            'no_reply' => __('No reply follow-up'),
        ];
    }

    public static function descriptions(): array
    {
        return [
            'welcome'  => __('Sent automatically when a customer starts a new conversation.'),
            'offline'  => __('Sent when a customer messages outside the shop business hours.'),
            'waiting'  => __('Sent while the conversation is waiting for an agent to be assigned.'),
            'no_reply' => __('Sent when a customer message has not been answered within a set time.'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'welcome'  => [
                'tooltip' => __('Welcome message'),
                'icon'    => 'fas fa-hand-wave',
                'class'   => 'text-green-500',
            ],
            'offline'  => [
                'tooltip' => __('Out of hours'),
                'icon'    => 'fas fa-moon',
                'class'   => 'text-indigo-500',
            ],
            'waiting'  => [
                'tooltip' => __('Waiting for agent'),
                'icon'    => 'fas fa-hourglass-half',
                'class'   => 'text-yellow-500',
            ],
            'no_reply' => [
                'tooltip' => __('No reply follow-up'),
                'icon'    => 'fas fa-clock',
                'class'   => 'text-orange-500',
            ],
        ];
    }
}
