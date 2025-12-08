<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Enums\Web\Redirect;

use App\Enums\EnumHelperTrait;

enum RedirectTypeEnum: string
{
    use EnumHelperTrait;

    case PERMANENT = '301';
    case TEMPORAL = '302';

}
