<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 May 2024 11:21:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Production;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ManufactureTasksTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case MANUFACTURE_TASKS = 'manufacture_tasks';
    case MANUFACTURE_TASKS_HISTORIES = 'manufacture_tasks_histories';

    public function blueprint(): array
    {
        return match ($this) {
            ManufactureTasksTabsEnum::MANUFACTURE_TASKS => [
                'title' => __('Tasks'),
                'icon' => 'fal fa-bars',
            ],
            ManufactureTasksTabsEnum::MANUFACTURE_TASKS_HISTORIES => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
