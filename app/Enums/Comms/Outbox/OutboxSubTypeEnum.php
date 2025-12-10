<?php

/*
 * Author: eka yudinata <ekayudinatha@gmail.com>
 * Created: Fri,  Dec 2025 12:15:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025,
 */

namespace App\Enums\Comms\Outbox;

use App\Enums\EnumHelperTrait;

enum OutboxSubTypeEnum: string
{
    use EnumHelperTrait;

    case REORDER_REMINDER = 'reorder_reminder';

}
