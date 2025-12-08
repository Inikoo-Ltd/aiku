<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:12:08 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\HumanResources;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ClockingMachineTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case CLOCKINGS = 'clockings';

    case HISTORY = 'history';
    case DATA = 'data';

    public function blueprint(): array
    {
        return match ($this) {
            ClockingMachineTabsEnum::CLOCKINGS => [
                'title' => __('Clockings'),
                'icon' => 'fal fa-clock',
            ],
            ClockingMachineTabsEnum::DATA => [
                'title' => __('Database'),
                'icon' => 'fal fa-database',
                'type' => 'icon',
                'align' => 'right',
            ],
            ClockingMachineTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            ClockingMachineTabsEnum::SHOWCASE => [
                'title' => __('Clocking machine'),
                'icon' => 'fas fa-info-circle',
            ],
        };
    }
}
