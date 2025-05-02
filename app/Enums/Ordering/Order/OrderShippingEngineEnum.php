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
    case TO_BE_DETERMINED = 'tbd';
    case TO_BE_DETERMINED_SET = 'tbd_set';
    case MANUAL = 'manual';
    case NO_APPLICABLE = 'no_applicable';// when order os for collection

}
