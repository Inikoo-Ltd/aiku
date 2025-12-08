<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:22:44 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Customer;

use App\Enums\EnumHelperTrait;

enum CustomerRejectReasonEnum: string
{
    use EnumHelperTrait;

    case SPAM = 'spam';
    case DUPLICATED = 'duplicated';
    case OTHER = 'other';
    case UNKNOWN = 'unknown';
}
