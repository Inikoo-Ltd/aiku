<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Sept 2024 21:55:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Adjustment;

use App\Enums\EnumHelperTrait;

enum AdjustmentTypeEnum: string
{
    use EnumHelperTrait;
    case ERROR_NET = 'error-net';
    case ERROR_TAX = 'error-tax';
    case CREDIT = 'credit';
}
