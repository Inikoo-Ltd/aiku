<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CustomerPlatformTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE      = 'showcase';

    public function blueprint(): array
    {
        return match ($this) {
            CustomerPlatformTabsEnum::SHOWCASE => [
                'title' => __('showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
        };
    }
}
