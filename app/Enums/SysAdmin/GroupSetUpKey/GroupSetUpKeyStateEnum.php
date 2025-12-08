<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jul 2024 15:32:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\GroupSetUpKey;

use App\Enums\EnumHelperTrait;

enum GroupSetUpKeyStateEnum: string
{
    use EnumHelperTrait;

    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case INSTALLING = 'installing';
    case CANCELLED = 'cancelled';
    case SUCCESS = 'success';

}
