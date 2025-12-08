<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 16:26:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStock;

use App\Enums\EnumHelperTrait;

enum OrgStockQuantityStatusEnum: string
{
    use EnumHelperTrait;

    case EXCESS = 'excess';
    case IDEAL = 'ideal';
    case LOW = 'low';
    case CRITICAL = 'critical';
    case OUT_OF_STOCK = 'out-of-stock';
    case ERROR = 'error';
}
