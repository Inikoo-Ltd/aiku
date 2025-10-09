<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterSubDepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE = 'showcase';
    case HISTORY  = 'history';
    case SUB_DEPARTMENTS = 'sub_departments';
    case IMAGES    = 'images';

    public function blueprint(): array
    {
        return match ($this) {

            MasterSubDepartmentTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Changelog'),
                'icon'  => 'fal fa-clock',

            ],
            MasterSubDepartmentTabsEnum::SUB_DEPARTMENTS => [
                'title' => __('Sub departments in shop'),
                'icon'  => 'fal fa-store',
            ],

            MasterSubDepartmentTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],

            MasterSubDepartmentTabsEnum::IMAGES => [
                'title' => __('Media'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
