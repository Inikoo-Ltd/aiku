<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 May 2024 17:42:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentLocationTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case PALLETS = 'pallets';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {

            FulfilmentLocationTabsEnum::PALLETS => [
                'title' => __('Pallets'),
                'icon' => 'fal fa-pallet',
            ],

            FulfilmentLocationTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],

            FulfilmentLocationTabsEnum::SHOWCASE => [
                'title' => __('Location'),
                'icon' => 'fas fa-info-circle',
            ],
        };
    }
}
