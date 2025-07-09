<?php

/*
 * author Arya Permana - Kirin
 * created on 09-07-2025-12h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Dropshipping;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaPortfoliosTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ACTIVE                       = 'active';
    case INACTIVE                     = 'inactive';

    public function blueprint(): array
    {
        return match ($this) {
            RetinaPortfoliosTabsEnum::ACTIVE => [
                'title' => __('active'),
                'icon'  => 'fal fa-bars',
            ],
            RetinaPortfoliosTabsEnum::INACTIVE => [
                'title' => __('inactive'),
                'icon'  => 'fal fa-bars',
            ],
        };
    }
}
