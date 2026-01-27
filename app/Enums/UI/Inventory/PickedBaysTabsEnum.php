<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:33:40 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PickedBaysTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case BAYS                       = 'bays';
    case BAYS_HISTORIES             = 'bays_histories';

    public function blueprint(): array
    {
        return match ($this) {
            PickedBaysTabsEnum::BAYS => [
                'title' => __('Bays'),
                'icon'  => 'fal fa-warehouse-alt',
            ],
            PickedBaysTabsEnum::BAYS_HISTORIES => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
