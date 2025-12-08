<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 02:24:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Miscellaneous;

use App\Enums\EnumHelperTrait;

enum GenderEnum: string
{
    use EnumHelperTrait;

    case MALE = 'male';
    case FEMALE = 'female';
    case OTHER = 'other';
}
