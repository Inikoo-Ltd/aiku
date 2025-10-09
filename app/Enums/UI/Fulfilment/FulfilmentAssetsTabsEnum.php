<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 15:19:39 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentAssetsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD                       = 'dashboard';
    case HISTORY                         = 'history';
    case ASSETS                          = 'assets';

    public function blueprint(): array
    {
        return match ($this) {
            FulfilmentAssetsTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            FulfilmentAssetsTabsEnum::ASSETS => [
                'title' => __('Assets list'),
                'icon'  => 'fal fa-bars',
                'type'  => 'icon',
                'align' => 'right',
            ],

            FulfilmentAssetsTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ]
        };
    }
}
