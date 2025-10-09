<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 02:49:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\Payment;

use App\Enums\EnumHelperTrait;

enum PaymentClassEnum: string
{
    use EnumHelperTrait;
    case ORDER  = 'order';
    case TOPUP   = 'topup';
}
