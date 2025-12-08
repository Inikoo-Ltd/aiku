<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Mar 2023 13:39:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\DeliveryNote;

use App\Enums\EnumHelperTrait;

enum DeliveryNoteTypeEnum: string
{
    use EnumHelperTrait;

    case ORDER = 'order';
    case REPLACEMENT = 'replacement';
}
