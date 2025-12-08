<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatActorTypeEnum: string
{
    use EnumHelperTrait;

    case GUEST = 'guest';
    case USER = 'user';
    case AGENT = 'agent';
    case SYSTEM = 'system';
    case AI = 'ai';

    public static function labels(): array
    {
        return [
            'guest' => __('Guest'),
            'user' => __('User'),
            'agent' => __('Agent'),
            'system' => __('System'),
            'ai' => __('AI'),
        ];
    }
}
