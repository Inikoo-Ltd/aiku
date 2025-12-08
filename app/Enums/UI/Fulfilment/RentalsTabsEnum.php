<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Apr 2024 15:05:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RentalsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case RENTALS = 'rentals';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            RentalsTabsEnum::RENTALS => [
                'title' => __('Rentals'),
                'icon' => 'fal fa-bars',
            ],

            RentalsTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ]
        };
    }
}
