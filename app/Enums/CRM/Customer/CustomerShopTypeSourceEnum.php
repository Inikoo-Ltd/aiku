<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Customer;

use App\Enums\EnumHelperTrait;

enum CustomerShopTypeSourceEnum: string
{
    use EnumHelperTrait;

    case CUSTOMER = 'customer';
    case STAFF = 'staff';
    case INFERRED = 'inferred';

    public static function labels(): array
    {
        return [
            'customer' => __('Told us'),
            'staff'    => __('Set by staff'),
            'inferred' => __('Inferred'),
        ];
    }
}
