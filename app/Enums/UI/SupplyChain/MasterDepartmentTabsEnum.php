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

enum MasterDepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE = 'showcase';
    case HISTORY  = 'history';




    public function blueprint(): array
    {
        return match ($this) {

            MasterDepartmentTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('changelog'),
                'icon'  => 'fal fa-clock',

            ],
            MasterDepartmentTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
