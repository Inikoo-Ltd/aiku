<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Apr 2023 12:27:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Import;

use App\Enums\EnumHelperTrait;

enum UploadRecordStatusEnum: string
{
    use EnumHelperTrait;

    case PROCESSING = 'processing';
    case COMPLETE = 'complete';
    case FAILED = 'failed';
}
