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
    case READY = 'ready'; // scheduled
    case LIVE = 'live';
    case CLOSED = 'closed'; // time finish or inactivated by force

    public function stateIcon(): array
    {
        return [
            'in-process' => [
                'icon'    => 'fad fa-stop',
                'class'   => 'text-red-500',
                'tooltip' => __('Inactive (will not show on the website)')
            ],
            'ready'      => [
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-green-500',
                'tooltip' => __('Scheduled')
            ],
            'live'       => [
                'icon'    => 'fal fa-tower-borcasting',
                'class'   => 'text-green-500 animate-pulse',
                'tooltip' => __('Active')
            ],
            'closed'     => [
                'icon'    => 'fal fa-stop',
                'class'   => 'text-red-500',
                'tooltip' => __('Finished')
            ]
        ];
    }

    public static function labels(): array
    {
        return [
            'in-process' => __('In construction'),
            'ready'      => __('Ready'),
            'live'       => __('Live'),
            'closed'     => __('Closed'),
        ];
    }
}
