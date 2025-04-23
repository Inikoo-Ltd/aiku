<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Apr 2025 19:14:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrdersInBasketTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ORDERS    = 'orders';

    public function blueprint(): array
    {
        return match ($this) {

            OrdersInBasketTabsEnum::ORDERS => [
                'title' => __('orders'),
                'icon'  => 'fal fa-bars',
            ],
        };
    }
}
