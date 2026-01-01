<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 21:35:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgStockStockHistoryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case STOCK_HISTORY = 'stock_history';

    public function blueprint(): array
    {
        return match ($this) {
            OrgStockStockHistoryTabsEnum::STOCK_HISTORY => [
                'title' => __('Stock history'),
                'icon'  => 'fal fa-scanner',
            ],
        };
    }
}
