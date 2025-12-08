<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 21:26:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\HumanResources\JobPosition;

use App\Enums\EnumHelperTrait;

enum JobPositionScopeEnum: string
{
    use EnumHelperTrait;

    case SHOPS = 'shops';
    case WAREHOUSES = 'warehouses';
    case FULFILMENTS = 'fulfilments';
    case FULFILMENTS_WAREHOUSES = 'fulfilments_warehouses';
    case PRODUCTIONS = 'productions';
    case ORGANISATION = 'organisation';
    case GROUP = 'group';
}
