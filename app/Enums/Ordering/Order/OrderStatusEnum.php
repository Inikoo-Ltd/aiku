<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:24:56 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Order;

use App\Enums\EnumHelperTrait;

enum OrderStatusEnum: string
{
    use EnumHelperTrait;

    case CREATING = 'creating';
    case PROCESSING = 'processing';
    case SETTLED = 'settled';
}
