<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 02:47:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\Payment;

use App\Enums\EnumHelperTrait;

enum PaymentSubsequentStatusEnum: string
{
    use EnumHelperTrait;

    case UNCHANGED = 'unchanged';
    case REFUNDED = 'refunded';
    case WITH_REFUND = 'with_refund';
}
