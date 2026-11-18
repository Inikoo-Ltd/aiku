<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\Comms\WhatsappBulkRun;

use App\Enums\EnumHelperTrait;

enum WhatsappBulkRunStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SENDING = 'sending';
    case SENT = 'sent';
    case STOPPED = 'stopped';

    public static function labels(): array
    {
        return [
            'in_process' => __('Preparing'),
            'sending'    => __('Sending'),
            'sent'       => __('Sent'),
            'stopped'    => __('Stopped'),
        ];
    }
}
