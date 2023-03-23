<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 Mar 2023 23:38:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Sales\Order;

use App\Enums\EnumHelperTrait;

enum OrderStateEnum: string
{
    use EnumHelperTrait;

    case CREATING  = 'creating';
    case SUBMITTED = 'submitted';
    case HANDLING  = 'handling';
    case PACKED    = 'packed';
    case FINALISED = 'finalised';
    case SETTLED   = 'settled';
}
