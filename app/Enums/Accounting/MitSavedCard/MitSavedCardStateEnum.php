<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 22:50:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\MitSavedCard;

use App\Enums\EnumHelperTrait;

enum MitSavedCardStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUCCESS = 'success';
    case FAILURE = 'failure';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case ERROR = 'error';



}
