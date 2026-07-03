<?php

namespace App\Enums\Workspace;

use App\Enums\EnumHelperTrait;

enum TaskStatusEnum: string
{
    use EnumHelperTrait;

    case PENDING        = 'pending';
    case WORKING_ON     = 'working_on';
    case READY          = 'ready';
    case CANT_BE_DONE   = 'cant_be_done';

    public static function labels(): array
    {
        return [
            'pending'      => __('Pending'),
            'working_on'   => __('Working on'),
            'ready'        => __('Ready'),
            'cant_be_done' => __("Can't be done"),
        ];
    }

    public static function badgeTheme(): array
    {
        return [
            'pending'      => 99,
            'working_on'   => 1,
            'ready'        => 3,
            'cant_be_done' => 7,
        ];
    }
}
