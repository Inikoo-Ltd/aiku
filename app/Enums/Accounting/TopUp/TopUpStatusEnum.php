<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Aug 2024 10:12:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\TopUp;

use App\Enums\EnumHelperTrait;

enum TopUpStatusEnum: string
{
    use EnumHelperTrait;
    case IN_PROCESS = 'in_process';
    case SUCCESS = 'success';
    case FAIL = 'fail';
}
