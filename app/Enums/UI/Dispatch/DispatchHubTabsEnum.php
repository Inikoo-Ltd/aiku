<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 */

namespace App\Enums\UI\Dispatch;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum DispatchHubTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';
    case PICKING_SESSION = 'picking_session';

    public function blueprint(): array
    {
        return match ($this) {
            DispatchHubTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            DispatchHubTabsEnum::PICKING_SESSION => [
                'title' => __('Picking Session'),
                'icon'  => 'fal fa-truck',
            ],
        };
    }
}
