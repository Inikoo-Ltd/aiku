<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Sep 2023 11:54:26 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Announcement;

use App\Enums\EnumHelperTrait;

enum AnnouncementStatusEnum: string
{
    use EnumHelperTrait;

    case INACTIVE  = 'inactive';
    case ACTIVE    = 'active';

    public static function labels(): array
    {
        return [
            'inactive'    => __('Inactive'),
            'active'      => __('Active')
        ];
    }

    public static function statusIcon(): array
    {
        return [
            'inactive'    => [
                'icon'    => 'fad fa-stop',
                'class'   => 'text-red-500',
                'tooltip' => __('Inactive')
            ],
            'active'      => [
                'icon'    => 'fal fa-broadcast-tower',
                'class'   => 'text-green-500 animate-pulse',
                'tooltip' => __('Active')
            ]
        ];
    }
}
