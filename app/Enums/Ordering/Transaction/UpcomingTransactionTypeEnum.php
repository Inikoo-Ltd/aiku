<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:25:18 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Transaction;

use App\Enums\EnumHelperTrait;

enum UpcomingTransactionTypeEnum: string
{
    use EnumHelperTrait;

    case GIFT = 'gift';
    case FOLLOW_ON = 'follow_on';
}
