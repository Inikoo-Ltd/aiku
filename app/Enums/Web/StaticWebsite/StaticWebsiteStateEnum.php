<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Web\StaticWebsite;

use App\Enums\EnumHelperTrait;

enum StaticWebsiteStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case LIVE = 'live';
    case CLOSED = 'closed';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'live'       => __('Live'),
            'closed'     => __('Closed'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-amber-500',
                'color'   => 'amber',
            ],
            'live'       => [
                'tooltip' => __('Live'),
                'icon'    => 'fal fa-broadcast-tower',
                'class'   => 'text-green-600',
                'color'   => 'green',
            ],
            'closed'     => [
                'tooltip' => __('Closed'),
                'icon'    => 'fal fa-skull',
                'class'   => 'text-gray-400',
                'color'   => 'gray',
            ],
        ];
    }
}
