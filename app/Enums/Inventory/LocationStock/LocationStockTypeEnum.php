<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 03:36:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\LocationStock;

use App\Enums\EnumHelperTrait;

enum LocationStockTypeEnum: string
{
    use EnumHelperTrait;

    case PICKING = 'picking';
    case STORING = 'storing';
}
