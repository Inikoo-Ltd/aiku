<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 12:29:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\TaxNumber;

use App\Enums\EnumHelperTrait;

enum TaxNumberStatusEnum: string
{
    use EnumHelperTrait;

    case VALID = 'valid';
    case INVALID = 'invalid';
    case NA = 'na';
    case UNKNOWN = 'unknown';
}
