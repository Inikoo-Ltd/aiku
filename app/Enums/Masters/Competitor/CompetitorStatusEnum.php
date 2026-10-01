<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Masters\Competitor;

use App\Enums\EnumHelperTrait;

enum CompetitorStatusEnum: string
{
    use EnumHelperTrait;

    case OK           = 'ok';
    case LOGIN_FAILED = 'login_failed';
    case BLOCKED      = 'blocked';
    case ERROR        = 'error';
}
