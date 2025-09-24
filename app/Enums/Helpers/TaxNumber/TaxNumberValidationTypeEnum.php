<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Sept 2025 22:47:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\TaxNumber;

use App\Enums\EnumHelperTrait;

enum TaxNumberValidationTypeEnum: string
{
    use EnumHelperTrait;

    case ONLINE = 'online';
    case MANUAL = 'manual';
}
