<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 19:12:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\AgentSupplierPurchaseOrders;

use App\Enums\EnumHelperTrait;

enum AgentSupplierPurchaseOrderDeliveryStateEnum: string
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
