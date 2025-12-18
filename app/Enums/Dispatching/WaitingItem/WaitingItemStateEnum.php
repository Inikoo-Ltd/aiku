<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Dec 2025 12:20:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\WaitingItem;

use App\Enums\EnumHelperTrait;

enum WaitingItemStateEnum: string
{
    use EnumHelperTrait;


    case TO_DO = 'to_do';
    case ESCALATED = 'escalated';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case CANCELLED = 'cancelled';


    public static function labels(): array
    {
        return [
            'to_do'       => __('To Do'),
            'escalated'   => __('Escalated'),
            'in_progress' => __('In Progress'),
            'done'        => __('Done'),
            'cancelled'   => __('Cancelled'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'to_do'       => [
                'tooltip' => __('To Do'),
                'icon'    => 'fal fa-inbox',
                'class'   => 'text-grey-500'

            ],
            'escalated'   => [
                'tooltip' => __('Escalated'),
                'icon'    => 'fal fa-inbox-out',
                'class'   => 'text-blue-400'

            ],
            'in_progress' => [
                'tooltip' => __('In Progress'),
                'icon'    => 'fal fa-wrench',
                'class'   => 'text-blue-500',

            ],
            'done'        => [
                'tooltip' => __('Done'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500',

            ],

            'cancelled' => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500'
            ]

        ];
    }
}
