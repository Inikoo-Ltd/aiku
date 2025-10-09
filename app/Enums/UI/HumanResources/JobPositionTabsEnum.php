<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:13:12 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\HumanResources;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum JobPositionTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE  = 'showcase';
    case EMPLOYEES = 'employees';
    case GUESTS    = 'guests';
    case ROLES     = 'roles';
    case HISTORY   = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            JobPositionTabsEnum::ROLES => [
                'title' => __('System roles'),
                'icon'  => 'fal fa-terminal',
            ],
            JobPositionTabsEnum::EMPLOYEES => [
                'title' => __('Employees'),
                'icon'  => 'fal fa-user-hard-hat',
            ],
            JobPositionTabsEnum::GUESTS => [
                'title' => __('Guests'),
                'icon'  => 'fal fa-user-alien',
            ],
            JobPositionTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            JobPositionTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
