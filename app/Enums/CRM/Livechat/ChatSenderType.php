<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatSenderType: string
{
     use EnumHelperTrait;

    case USER = 'user';
    case GUEST = 'guest';
    case AGENT = 'agent';
    case SYSTEM = 'system';
    case AI = 'ai';

    public static function labels(): array
    {
        return [
            'user' => __('User'),
            'guest' => __('Guest'),
            'agent' => __('Agent'),
            'system' => __('System'),
            'ai' => __('AI'),
        ];
    }
}
