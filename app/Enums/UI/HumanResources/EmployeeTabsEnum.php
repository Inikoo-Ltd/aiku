<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:14:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\HumanResources;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum EmployeeTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case HISTORY = 'history';
    case ATTACHMENTS = 'attachments';

    public function blueprint(): array
    {
        return match ($this) {
            EmployeeTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon' => 'fal fa-paperclip',
                'type' => 'icon',
                'align' => 'right',
            ],

            EmployeeTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            EmployeeTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon' => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
