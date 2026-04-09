<?php

/*
 * Author: Vika Aqordi
 * Created on 09-04-2026-10h-51m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Enums\UI\Dispatch;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WaitingItemsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case GROUPED = 'grouped';
    case ITEMIZED = 'itemized';

    public function blueprint(): array
    {
        return match ($this) {
            WaitingItemsTabsEnum::GROUPED => [
                'title' => __('Group by delivery'),
                'icon'  => 'fal fa-truck',
            ],
            WaitingItemsTabsEnum::ITEMIZED => [
                'title' => __('Pick by location'),
                'icon'  => 'fal fa-inventory',
            ],
        };
    }
}
