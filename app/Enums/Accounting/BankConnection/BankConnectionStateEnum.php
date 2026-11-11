<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\BankConnection;

use App\Enums\EnumHelperTrait;

enum BankConnectionStateEnum: string
{
    use EnumHelperTrait;

    case ACTIVE = 'active';
    case ERROR = 'error';
    case DISABLED = 'disabled';
}
