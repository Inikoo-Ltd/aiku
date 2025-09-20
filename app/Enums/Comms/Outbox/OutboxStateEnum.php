<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\Outbox;

use App\Enums\EnumHelperTrait;

enum OutboxStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'active'     => __('Active'),
            'suspended'  => __('Suspended')
        ];
    }

    public static function icon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('In Process'),
                'class'   => 'text-orange-400',
                'icon'    => 'fal fa-seedling',
                'app'     => [
                    'name' => 'Outbox',
                ]
            ],
            'active'     => [
                'tooltip' => __('Active'),
                'icon'    => 'fal fa-broadcast-tower',
                'class'   => 'text-emerald-500',
                'color'   => 'emerald',
            ],
            'suspended'  => [
                'tooltip' => __('Suspended'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
            ]
        ];
    }
}
