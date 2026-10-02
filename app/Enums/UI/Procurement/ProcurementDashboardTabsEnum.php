<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProcurementDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case STOCK_OUTS = 'stock_outs';
    case SEARCH_DEMAND = 'search_demand';

    public function blueprint(): array
    {
        return match ($this) {
            ProcurementDashboardTabsEnum::STOCK_OUTS => [
                'title' => __('Stock outs'),
                'icon'  => 'fal fa-box-open',
            ],
            ProcurementDashboardTabsEnum::SEARCH_DEMAND => [
                'title' => __('Customers asked for'),
                'icon'  => 'fal fa-cart-plus',
            ],
        };
    }
}
