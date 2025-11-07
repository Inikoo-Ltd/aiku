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
    case SYSTEM_CUSTOMER        = 'system_customer';
    case ADMIN_CUSTOMER         = 'admin_customer';
    case USER_CUSTOMER          = 'user_customer';
    case OTHER                  = 'other';

    /**
     * Get a human-readable version of the enum value.
     *
     * @return string
     */
    public function pretty(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
