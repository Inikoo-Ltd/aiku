<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Apr 2023 12:27:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Export;

use App\Enums\EnumHelperTrait;

enum ExportTypeEnum: string
{
    use EnumHelperTrait;

    case XLSX = 'xlsx';
    case CSV = 'csv';
    case PDF = 'pdf';
}
