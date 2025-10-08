<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Sep 2023 11:54:26 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Announcement;

use App\Enums\EnumHelperTrait;

enum AnnouncementStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in-process';
    case READY      = 'ready';
    case CLOSED     = 'closed';

    public function stateIcon(): array
    {
        return [
            'in-process'    => [
                'icon'    => 'fad fa-stop',
                'class'   => 'text-red-500',
                'tooltip' => __('Inactive (will not show on the website)')
            ],
            'ready'      => [
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-green-500 animate-pulse',
                'tooltip' => __('Active (will show if possible)')
            ],
            'closed'      => [
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-green-500 animate-pulse',
                'tooltip' => __('Closed (will show if possible)')
            ]
        ];
    }

    public static function labels(): array
    {
        return [
            'in-process' => __('In construction'),
            'ready'      => __('Ready'),
            'closed'     => __('Closed'),
        ];
    }
}
