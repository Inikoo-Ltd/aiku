<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Apr 2023 17:11:07 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\PurchaseOrder;

use App\Enums\EnumHelperTrait;

enum PurchaseOrderDeliveryStateEnum: string
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
