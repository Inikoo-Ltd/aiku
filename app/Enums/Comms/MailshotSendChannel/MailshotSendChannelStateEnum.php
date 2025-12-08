<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\MailshotSendChannel;

use App\Enums\EnumHelperTrait;

enum MailshotSendChannelStateEnum: string
{
    use EnumHelperTrait;

    case READY = 'ready';
    case SENDING = 'sending';
    case SENT = 'sent';
    case STOPPED = 'stopped';

}
