<?php

/*
 * Author: Vika Aqordi
 * Created on 04-02-2026-11h-06m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PickedBayTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE               = 'showcase';
    case HISTORY              = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            PickedBayTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            PickedBayTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
