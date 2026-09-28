<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\SupplierMessage;

use App\Enums\EnumHelperTrait;

enum SupplierMessageDirectionEnum: string
{
    use EnumHelperTrait;

    case INBOUND  = 'inbound';
    case OUTBOUND = 'outbound';
}
