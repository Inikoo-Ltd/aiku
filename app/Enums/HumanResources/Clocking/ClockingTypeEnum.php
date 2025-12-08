<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Jun 2023 23:19:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\HumanResources\Clocking;

use App\Enums\EnumHelperTrait;

enum ClockingTypeEnum: string
{
    use EnumHelperTrait;

    case CLOCKING_MACHINE = 'clocking-machine';
    case MANUAL = 'manual';
    case SELF_CHECK = 'self-check';
    case SYSTEM = 'system';

}
