<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 Dec 2023 00:29:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\WebUser;

use App\Enums\EnumHelperTrait;

enum WebUserAuthTypeEnum: string
{
    use EnumHelperTrait;

    case DEFAULT = 'default';
    case AURORA = 'aurora';
}
