<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatEventActorTypeEnum: string
{
    use EnumHelperTrait;


    case USER = 'user';
    case AGENT = 'agent';
    case SYSTEM = 'system';
    case GUEST = 'guest';
    case AI = 'ai';

    public static function labels(): array
    {
        return [
            'user'   => __('User'),
            'agent'  => __('Agent'),
            'system' => __('System'),
            'guest'  => __('Guest'),
            'ai'     => __('AI'),
        ];
    }
}
