<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:32:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Production;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ArtefactTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    // case DASHBOARD               = 'dashboard';
    case MANUFACTURE_TASKS = 'manufacture_tasks';
    case RAW_MATERIALS = 'raw_materials';
    case HISTORY = 'history';
    // case DATA                    = 'data';

    public function blueprint(): array
    {
        return match ($this) {
            // ArtefactTabsEnum::DASHBOARD => [
            //     'title' => __('Stats'),
            //     'icon'  => 'fal fa-chart-line',
            // ],
            ArtefactTabsEnum::MANUFACTURE_TASKS => [
                'title' => __('Manufacture tasks'),
                'icon' => 'fal fa-hamsa',
            ],
            ArtefactTabsEnum::RAW_MATERIALS => [
                'title' => __('Raw materials'),
                'icon' => 'fal fa-hamsa',
            ],
            // ArtefactTabsEnum::DATA => [
            //     'align' => 'right',
            //     'type'  => 'icon',
            //     'title' => __('Data'),
            //     'icon'  => 'fal fa-database',
            // ],
            ArtefactTabsEnum::HISTORY => [
                'align' => 'right',
                'type' => 'icon',
                'title' => __('History'),
                'icon' => 'fal fa-clock',
            ],
            ArtefactTabsEnum::SHOWCASE => [
                'title' => __('Warehouse'),
                'icon' => 'fas fa-info-circle',
            ],
        };
    }
}
