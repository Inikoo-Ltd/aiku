<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 12:29:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\TaxNumber;

use App\Enums\EnumHelperTrait;

enum TaxNumberTypeEnum: string
{
    use EnumHelperTrait;

    case EU_VAT = 'eu-vat';
    case GB_VAT = 'gb-vat';
    case OTHER = 'other';
    case UNKNOWN = 'unknown';
}
