<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-15h-21m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PurgeTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case PURGED_ORDERS = 'purged_orders';

    public function blueprint(): array
    {
        return match ($this) {
            PurgeTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon' => 'fal fa-info-circle',
            ],
            PurgeTabsEnum::PURGED_ORDERS => [
                'title' => __('Purged orders'),
                'icon' => 'fal fa-dollar-sign',
            ],
        };
    }
}
