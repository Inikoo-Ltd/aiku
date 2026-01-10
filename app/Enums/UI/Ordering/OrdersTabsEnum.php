<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 09:29:02 Central Indonesia Time, Sanur, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrdersTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ORDERS    = 'orders';
    case LAST_ORDERS    = 'last_orders';
    case EXCESS_ORDERS    = 'excess_orders';
    case ORDERS_WITH_REPLACEMENTS    = 'orders_with_replacements';
    case STATS     = 'stats';
    case HISTORY   = 'history';

    public function blueprint(): array
    {
        return match ($this) {

            OrdersTabsEnum::ORDERS => [
                'title' => __('All orders'),
                'icon'  => 'fal fa-bars',
            ],
            OrdersTabsEnum::LAST_ORDERS => [
                'title' => __('Last orders'),
                'icon'  => 'fal fa-flux-capacitor',
            ],
            OrdersTabsEnum::EXCESS_ORDERS => [
                'title' => __('Overpaid orders'),
                'icon'  => 'fal fa-arrow-from-bottom',
            ],
            OrdersTabsEnum::STATS => [
                'title' => __('Stats'),
                'icon'  => 'fal fa-chart-pie',
            ],
            OrdersTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
            OrdersTabsEnum::ORDERS_WITH_REPLACEMENTS => [
                'title' => __('Orders with replacements'),
                'icon'  => 'fal fa-sync-alt',

            ]
        };
    }
}
