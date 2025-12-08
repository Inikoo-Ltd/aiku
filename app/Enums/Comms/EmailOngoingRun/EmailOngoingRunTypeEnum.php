<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Dec 2024 17:02:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\EmailOngoingRun;

use App\Enums\EnumHelperTrait;

enum EmailOngoingRunTypeEnum: string
{
    use EnumHelperTrait;

    case TRANSACTIONAL = 'transactional';
    case BULK = 'bulk';
    case PUSH = 'push';

}
