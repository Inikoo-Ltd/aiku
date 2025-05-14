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

    public function blueprint(): array
    {
        return match ($this) {
            ShipperTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fas fa-info-circle',
            ],
        };
    }
}
