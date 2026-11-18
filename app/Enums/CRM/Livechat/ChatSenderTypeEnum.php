<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatSenderTypeEnum: string
{
    use EnumHelperTrait;

    case USER = 'user';
    case GUEST = 'guest';
    case AGENT = 'agent';
    case SYSTEM = 'system';
    case SYSTEM_CAMPAIGN = 'system_campaign';
    case SYSTEM_NOTIFICATION = 'system_notification';
    case AI = 'ai';

    public static function labels(): array
    {
        return [
            'user' => __('User'),
            'guest' => __('Guest'),
            'agent' => __('Agent'),
            'system' => __('System'),
            'system_campaign' => __('Campaign'),
            'system_notification' => __('Notification'),
            'ai' => __('AI'),
        ];
    }
}
