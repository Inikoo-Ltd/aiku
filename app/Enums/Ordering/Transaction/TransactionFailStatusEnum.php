<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Aug 2024 11:46:35 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Transaction;

use App\Enums\EnumHelperTrait;

enum TransactionFailStatusEnum: string
{
    use EnumHelperTrait;

    case OUT_OF_STOCK = 'out_of_stock';
    case NO_AUTHORISED = 'no_authorised';
    case NOT_FOUND = 'not_found';
    case OTHER = 'other';

}
