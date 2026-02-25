<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:25:18 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Transaction;

use App\Enums\EnumHelperTrait;

enum TransactionStateEnum: string
{
    use EnumHelperTrait;

    case CREATING = 'creating';
    case SUBMITTED = 'submitted';
    case IN_WAREHOUSE = 'in_warehouse';
    case HANDLING = 'handling';
    case HANDLING_BLOCKED = 'handling_blocked';
    case PICKED = 'picked';
    case PACKING = 'packing';
    case PACKED = 'packed';
    case FINALISED = 'finalised';
    case DISPATCHED = 'dispatched';
    case CANCELLED = 'cancelled';
}
