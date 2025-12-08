<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatAssignmentAssignedByEnum: string
{
    use EnumHelperTrait;


    case USER = 'user';
    case AGENT = 'agent';
    case SYSTEM = 'system';

    public static function labels(): array
    {
        return [
            'user' => __('User'),
            'agent' => __('Agent'),
            'system' => __('System'),
        ];
    }
}
