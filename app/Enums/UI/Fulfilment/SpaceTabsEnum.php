<?php

/*
 * author Arya Permana - Kirin
 * created on 30-01-2025-16h-38m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SpaceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';

    public function blueprint(): array
    {
        return match ($this) {
            SpaceTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon' => 'fas fa-info-circle',
            ],
        };
    }
}
