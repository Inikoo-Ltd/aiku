<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\SupplierMessage;

use App\Enums\EnumHelperTrait;

enum SupplierMessageRoutedByEnum: string
{
    use EnumHelperTrait;

    case THREAD  = 'thread';
    case ADDRESS = 'address';
    case DOMAIN  = 'domain';
    case MANUAL  = 'manual';
    case PURCHASE_ORDER = 'purchase_order';
}
