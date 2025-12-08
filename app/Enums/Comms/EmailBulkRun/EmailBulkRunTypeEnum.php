<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 13:30:40 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\EmailBulkRun;

use App\Enums\EnumHelperTrait;

enum EmailBulkRunTypeEnum: string
{
    use EnumHelperTrait;

    case BASKET_LOW_STOCK = 'basket_low_stock';
    case REORDER_REMINDER = 'reorder_reminder';
    case OOS_NOTIFICATION = 'oos_notification';

}
