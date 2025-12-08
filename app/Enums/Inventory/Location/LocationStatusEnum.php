<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 03:19:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\Location;

use App\Enums\EnumHelperTrait;

enum LocationStatusEnum: string
{
    use EnumHelperTrait;

    case OPERATIONAL = 'operational';
    case BROKEN = 'broken';
}
