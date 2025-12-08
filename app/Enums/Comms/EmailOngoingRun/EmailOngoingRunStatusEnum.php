<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Dec 2024 12:01:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\EmailOngoingRun;

use App\Enums\EnumHelperTrait;

enum EmailOngoingRunStatusEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'active' => __('Active'),
            'suspended' => __('Suspended'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [

                'tooltip' => __('In Process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-indigo-500',
            ],
            'active' => [

                'tooltip' => __('Active'),
                'icon' => 'fal fa-paper-plane',
                'class' => 'animate-pulse',
            ],
            'suspended' => [

                'tooltip' => __('Suspended'),
                'icon' => 'fal fa-paper-plane',
                'class' => 'text-red-600',
            ],

        ];
    }
}
