<?php

namespace App\Enums\Web\WebsiteDialog;

use App\Enums\EnumHelperTrait;

enum WebsiteDialogStatusEnum: string
{
    use EnumHelperTrait;

    case INACTIVE = 'inactive';
    case ACTIVE = 'active';

    public static function labels(): array
    {
        return [
            'inactive' => __('Inactive'),
            'active'   => __('Active')
        ];
    }

    public static function statusIcon(): array
    {
        return [
            'inactive' => [
                'icon'    => 'fad fa-stop',
                'class'   => 'text-red-500',
                'tooltip' => __('Inactive')
            ],
            'active'   => [
                'icon'    => 'fal fa-broadcast-tower',
                'class'   => 'text-green-500 animate-pulse',
                'tooltip' => __('Active')
            ]
        ];
    }
}
