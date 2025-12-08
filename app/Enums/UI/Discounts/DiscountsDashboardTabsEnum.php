<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 14:31:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Discounts;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum DiscountsDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';

    public function blueprint(): array
    {
        return match ($this) {
            DiscountsDashboardTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon' => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
