<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:45:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Setting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';

    public function blueprint(): array
    {
        return match ($this) {
            FulfilmentDashboardTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
