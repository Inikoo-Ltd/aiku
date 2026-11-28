<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\CrossDispatch;

use App\Enums\EnumHelperTrait;

enum CrossDispatchChargeStateEnum: string
{
    use EnumHelperTrait;

    case OPEN = 'open';
    case REVERSED = 'reversed';
}
