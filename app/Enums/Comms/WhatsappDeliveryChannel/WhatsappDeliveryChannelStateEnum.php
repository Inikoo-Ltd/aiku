<?php

namespace App\Enums\Comms\WhatsappDeliveryChannel;

use App\Enums\EnumHelperTrait;

enum WhatsappDeliveryChannelStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case READY      = 'ready';
    case SENDING    = 'sending';
    case SENT       = 'sent';
    case STOPPED    = 'stopped';
}
