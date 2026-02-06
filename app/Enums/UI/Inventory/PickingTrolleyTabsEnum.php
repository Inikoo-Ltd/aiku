<?php

/*
 * Author: Vika Aqordi
 * Created on 04-02-2026-10h-56m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PickingTrolleyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE                       = 'showcase';
    // case TROLLEYS_HISTORIES             = 'trolleys_histories';

    public function blueprint(): array
    {
        return match ($this) {
            PickingTrolleyTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            // PickingTrolleyTabsEnum::TROLLEYS_HISTORIES => [
            //     'title' => __('History'),
            //     'icon'  => 'fal fa-clock',
            //     'type'  => 'icon',
            //     'align' => 'right'
            // ],
        };
    }
}
