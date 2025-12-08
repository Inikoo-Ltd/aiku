<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 14:50:49 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\StockDeliveryItem;

use App\Enums\EnumHelperTrait;

enum StockDeliveryItemStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case CONFIRMED = 'confirmed';
    case READY_TO_SHIP = 'ready_to_ship';
    case DISPATCHED = 'dispatched';
    case RECEIVED = 'received';
    case CHECKED = 'checked';
    case PLACED = 'placed';
    case CANCELLED = 'cancelled';
    case NOT_RECEIVED = 'not_received';

}
