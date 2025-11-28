<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:50:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterDepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    /*     case CONTENT = 'content'; */
    case DEPARTMENTS = 'departments';
    case SALES = 'sales';
    case HISTORY = 'history';
    case IMAGES = 'images';


    public function blueprint(): array
    {
        return match ($this) {

            MasterDepartmentTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            MasterDepartmentTabsEnum::DEPARTMENTS => [
                'title' => __('Departments in shop'),
                'icon'  => 'fal fa-store',
            ],

            MasterDepartmentTabsEnum::IMAGES => [
                'title' => __('Media'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon',
                'align' => 'right',
            ],
            MasterDepartmentTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            MasterDepartmentTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            /*   MasterDepartmentTabsEnum::CONTENT => [
                  'title' => __('Content'),
                  'icon'  => 'fal fa-quote-left',
              ], */
        };
    }
}
