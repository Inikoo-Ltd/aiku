<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\Mailshot;

use App\Enums\EnumHelperTrait;

enum MailshotStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case READY = 'ready';
    case SCHEDULED = 'scheduled';
    case SENDING = 'sending';
    case SENT = 'sent';
    case CANCELLED = 'cancelled';
    case STOPPED = 'stopped';

    public static function labels(): array
    {
        return [
            'in_process' => __('In process'),
            'ready' => __('Ready'),
            'scheduled' => __('Scheduled'),
            'sending' => __('Sending'),
            'sent' => __('Sent'),
            'cancelled' => __('Cancelled'),
            'stopped' => __('Stopped'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [

                'tooltip' => __('In process'),
                'icon' => 'fal fa-seedling',
                'class' => 'text-indigo-500',

            ],
            'ready' => [

                'tooltip' => __('Ready'),
                'icon' => 'fal fa-spell-check',
                'class' => 'text-green-500',

            ],
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
            'cancelled' => [

                'tooltip' => __('cancelled'),
                'icon' => 'fal fa-paper-plane',
                'class' => 'text-red-600',
            ],
            'stopped' => [

                'tooltip' => __('stopped'),
                'icon' => 'fas fa-stop',
                'class' => 'text-red-600',
            ],

        ];
    }
}
