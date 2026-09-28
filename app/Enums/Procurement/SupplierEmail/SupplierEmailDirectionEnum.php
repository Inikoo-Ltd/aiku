<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\SupplierEmail;

use App\Enums\EnumHelperTrait;

enum SupplierEmailDirectionEnum: string
{
    use EnumHelperTrait;

    case INBOUND  = 'inbound';
    case OUTBOUND = 'outbound';
}
