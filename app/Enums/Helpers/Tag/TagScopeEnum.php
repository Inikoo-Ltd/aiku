<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\Helpers\Tag;

use App\Enums\EnumHelperTrait;

enum TagScopeEnum: string
{
    use EnumHelperTrait;

    case PRODUCT_PROPERTY       = 'product_property';
    case OTHER                  = 'other';
}
