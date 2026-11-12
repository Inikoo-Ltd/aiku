<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\BankConnection;

use App\Enums\EnumHelperTrait;

enum BankConnectionProviderEnum: string
{
    use EnumHelperTrait;

    case WISE = 'wise';
    case FIO = 'fio';
    case OPEN_BANKING = 'open_banking';
}
