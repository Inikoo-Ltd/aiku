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

    case ITEMIZED                   = 'itemized';
    case GROUPED_BY_DELIVERY_NOTE   = 'grouped_by_delivery_note';
    case GROUPED_BY_ITEM            = 'grouped_by_item';

    public function blueprint(): array
    {
        return match ($this) {
            WaitingItemsTabsEnum::ITEMIZED => [
                'title' => __('Item by item'),
                'icon'  => 'fal fa-box',
            ],
            WaitingItemsTabsEnum::GROUPED_BY_DELIVERY_NOTE => [
                'title' => __('Grouping by delivery note'),
                'icon'  => 'fal fa-truck',
            ],
            WaitingItemsTabsEnum::GROUPED_BY_ITEM => [
                'title' => __('Grouping by item'),
                'icon'  => 'fal fa-boxes',
            ],
        };
    }
}
