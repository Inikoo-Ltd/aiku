<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 11:54:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStockAuditDelta;

use App\Enums\EnumHelperTrait;

enum OrgStockAuditDeltaTypeEnum: string
{
    use EnumHelperTrait;

    case ADDITION = 'addition';
    case SUBTRACTION = 'subtraction';
    case NO_CHANGE = 'no_change';

}
