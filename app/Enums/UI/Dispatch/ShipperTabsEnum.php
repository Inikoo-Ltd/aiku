<?php

/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-11h-12m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Dispatch;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ShipperTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            ShipperTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fas fa-info-circle',
            ],
            ShipperTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
