<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 14 Jan 2024 19:36:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Transfers\FetchRecord;

use App\Enums\EnumHelperTrait;

enum FetchRecordTypeEnum: string
{
    use EnumHelperTrait;

    case STORE       = 'store';
    case UPDATE      = 'update';
    case ERROR       = 'error';
    case FETCH_ERROR = 'fetch_error';


}
