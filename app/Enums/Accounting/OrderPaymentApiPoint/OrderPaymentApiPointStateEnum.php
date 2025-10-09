<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Jul 2025 00:13:15 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\OrderPaymentApiPoint;

use App\Enums\EnumHelperTrait;

enum OrderPaymentApiPointStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUCCESS = 'success';
    case FAILURE = 'failure';
    case ERROR = 'error';
}
