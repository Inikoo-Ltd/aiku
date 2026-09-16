<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatChannelEnum: string
{
    use EnumHelperTrait;

    case WEBSITE = 'website';
    case EMAIL = 'email';

    public static function labels(): array
    {
        return [
            'website' => __('Website'),
            'email' => __('Email'),
        ];
    }
}
