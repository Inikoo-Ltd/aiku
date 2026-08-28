<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStockMovement;

use App\Enums\EnumHelperTrait;

enum OrgStockMovementCostStatusEnum: string
{
    use EnumHelperTrait;

    case PROVISIONAL = 'provisional';
    case DELIVERY = 'delivery';
    case COSTED = 'costed';
}
