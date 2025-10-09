<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 14:40:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SysAdmin;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SysAdminAnalyticsDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';

    public function blueprint(): array
    {
        return match ($this) {
            SysAdminAnalyticsDashboardTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
