<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Mar 2023 13:45:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\DeliveryNoteItem;

use App\Enums\EnumHelperTrait;

enum DeliveryNoteItemStateEnum: string
{
    use EnumHelperTrait;


    case ON_HOLD = 'on-hold';
    case HANDLING = 'handling';
    case PACKED = 'packed';
    case FINALISED = 'finalised';
    case SETTLED = 'settled';
}
