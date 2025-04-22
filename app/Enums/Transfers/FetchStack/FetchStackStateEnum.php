<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Apr 2025 11:08:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Transfers\FetchStack;

use App\Enums\EnumHelperTrait;

enum FetchStackStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SEND_TO_QUEUE = 'send_to_queue';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case ERROR = 'error';


}
