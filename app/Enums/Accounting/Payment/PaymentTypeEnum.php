<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 02:49:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\Payment;

use App\Enums\EnumHelperTrait;

enum PaymentTypeEnum: string
{
    use EnumHelperTrait;
    case PAYMENT = 'payment';
    case REFUND = 'refund';

}
