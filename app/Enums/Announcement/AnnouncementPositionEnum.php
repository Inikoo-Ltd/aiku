<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Announcement;

use App\Enums\EnumHelperTrait;

enum AnnouncementPositionEnum: string
{
    use EnumHelperTrait;

    case TOP_BAR     = 'top-bar';
    case BOTTOM_MENU = 'bottom-menu';
    case TOP_FOOTER  = 'top-footer';

    public static function labels(): array
    {
        return [
            'top-bar'     => __('Top bar'),
            'bottom-menu' => __('Below the Menu'),
            'top-footer'  => __('Above the Footer'),
        ];
    }
}
