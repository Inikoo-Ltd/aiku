<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 18:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WarehouseTeamTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';
    case CLOCKINGS = 'clockings';

    public function blueprint(): array
    {
        return match ($this) {
            WarehouseTeamTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            WarehouseTeamTabsEnum::CLOCKINGS => [
                'title' => __('Clockings'),
                'icon'  => 'fal fa-clock',
            ],
        };
    }
}
