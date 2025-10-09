<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Sept 2025 22:38:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Order;

use App\Enums\EnumHelperTrait;

enum OrderToBePaidByEnum: string
{
    use EnumHelperTrait;

    case BANK = 'bank';
    case CASH_ON_DELIVERY = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::BANK => __('Bank transfer'),
            self::CASH_ON_DELIVERY => __('Cash on delivery'),
        };
    }
}
