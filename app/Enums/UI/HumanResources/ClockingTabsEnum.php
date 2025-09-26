<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:12:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\HumanResources;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ClockingTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE           = 'showcase';

    case HISTORY            = 'history';
    case DATA               = 'data';



    public function blueprint(): array
    {
        return match ($this) {
            ClockingTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ClockingTabsEnum::DATA => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('Data'),
                'icon'  => 'fal fa-database',
            ],
            ClockingTabsEnum::SHOWCASE => [
                'title' => __('Clocking'),
                'icon'  => 'fas fa-info-circle',
            ],
        };
    }
}
