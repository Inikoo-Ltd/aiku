<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Dec 2024 01:58:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\EmailPush;

use App\Enums\EnumHelperTrait;

enum EmailPushExitStatusEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case FINISHED = 'finished';
    case BREAK_POINT = 'break_point';
    case UNSUBSCRIBED = 'unsubscribed'; // Unsubscribed by recipient
    case CANCELLED = 'cancelled'; // Cancelled by user

    public static function labels(): array
    {
        return [
            'in_process' => __('In Process'),
            'finished' => __('Finished'),
            'break_point' => __('Break Point'),
            'unsubscribed' => __('Unsubscribed'),
            'cancelled' => __('Cancelled'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [

                'tooltip' => __('In Process'),
                'icon' => 'fal fa-spinner-third',
                'class' => 'text-indigo-500',
            ],
            'finished' => [

                'tooltip' => __('Finished'),
                'icon' => 'fal fa-check',
                'class' => 'text-green-600',
            ],
            'break_point' => [

                'tooltip' => __('Break Point'),
                'icon' => 'fal fa-exclamation-triangle',
                'class' => 'text-yellow-600',
            ],
            'unsubscribed' => [

                'tooltip' => __('Unsubscribed'),
                'icon' => 'fal fa-user-slash',
                'class' => 'text-red-600',
            ],
            'cancelled' => [

                'tooltip' => __('Cancelled'),
                'icon' => 'fal fa-ban',
                'class' => 'text-red-600',
            ],

        ];
    }
}
