<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 May 2024 20:15:32 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Barcode;

use App\Enums\EnumHelperTrait;

enum BarcodeStatusEnum: string
{
    use EnumHelperTrait;

    case AVAILABLE = 'available';
    case USED = 'used';
    case RESERVED = 'reserved';

}
