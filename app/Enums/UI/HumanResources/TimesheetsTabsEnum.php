<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:14:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\HumanResources;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TimesheetsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ALL_EMPLOYEES = 'employees';
    case PER_EMPLOYEE  = 'employee';

    public function blueprint(): array
    {
        return match ($this) {
            TimesheetsTabsEnum::ALL_EMPLOYEES => [
                'title' => __('All employees'),
                'icon'  => 'fal fa-users',
            ],
            TimesheetsTabsEnum::PER_EMPLOYEE => [
                'title' => __('Per employee'),
                'icon'  => 'fal fa-user',
            ]
        };
    }
}
