<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 11:40:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Order;

use App\Enums\EnumHelperTrait;

enum OrderShippingEngineEnum: string
{
    use EnumHelperTrait;

    case AUTO = 'auto';
    case TO_BE_CONFIRMED = 'tbc'; // todo delete this
    case TO_BE_CONFIRMED_SET = 'tbc_set'; // todo delete this
    case MANUAL = 'manual';
    case NO_APPLICABLE = 'no_applicable';// when order os for collection

}
