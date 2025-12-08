<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Dec 2024 01:58:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\EmailPush;

use App\Enums\EnumHelperTrait;

enum EmailPushStateEnum: string
{
    use EnumHelperTrait;

    case SCHEDULED = 'scheduled';
    case SENDING = 'sending';
    case SENT = 'sent';
    case CANCELLED = 'terminated';

    public static function labels(): array
    {
        return [
            'scheduled' => __('Scheduled'),
            'sending' => __('Sending'),
            'sent' => __('Sent'),
            'terminated' => __('Terminated'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'scheduled' => [

                'tooltip' => __('Scheduled'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-indigo-500',

            ],
            'sending' => [

                'tooltip' => __('sending'),
                'icon' => 'fal fa-paper-plane',
                'class' => 'animate-pulse',

            ],
            'sent' => [

                'tooltip' => __('sent'),
                'icon' => 'fal fa-paper-plane',
                'class' => 'text-green-600',
            ],
            'terminated' => [

                'tooltip' => __('terminated'),
                'icon' => 'fal fa-paper-plane',
                'class' => 'text-red-600',
            ],

        ];
    }
}
