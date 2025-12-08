<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\EmailDeliveryChannel;

use App\Enums\EnumHelperTrait;

enum EmailDeliveryChannelStateEnum: string
{
    use EnumHelperTrait;

    case READY = 'ready';
    case SENDING = 'sending';
    case SENT = 'sent';
    case STOPPED = 'stopped';

}
