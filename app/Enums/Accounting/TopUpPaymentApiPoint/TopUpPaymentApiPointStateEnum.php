<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 22:44:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\TopUpPaymentApiPoint;

use App\Enums\EnumHelperTrait;

enum TopUpPaymentApiPointStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUCCESS = 'success';
    case FAILURE = 'failure';
    case ERROR = 'error';

}
