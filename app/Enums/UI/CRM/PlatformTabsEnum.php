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

enum PlatformTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE      = 'showcase';
    case CHANNELS      = 'channels';
    case PRODUCTS      = 'products';

    public function blueprint(): array
    {
        return match ($this) {
            PlatformTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            PlatformTabsEnum::PRODUCTS => [
                'title' => __('Portfolios'),
                'icon'  => 'fal fa-cube',
            ],
            PlatformTabsEnum::CHANNELS => [
                'title' => __('Channels'),
                'icon'  => 'fal fa-user-friends',
            ],
        };
    }
}
