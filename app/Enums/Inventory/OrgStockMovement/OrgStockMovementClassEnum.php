<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Sept 2024 16:41:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStockMovement;

use App\Enums\EnumHelperTrait;

enum OrgStockMovementClassEnum: string
{
    use EnumHelperTrait;

    case MOVEMENT = 'movement';
    case INFO = 'info';
    case HELPER = 'helper';
}
