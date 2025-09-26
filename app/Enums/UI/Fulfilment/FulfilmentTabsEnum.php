<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 15:17:01 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case DASHBOARD                     = 'dashboard';

    case HISTORY                       = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            FulfilmentTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-tachometer-alt',
            ],

            FulfilmentTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ]
        };
    }
}
