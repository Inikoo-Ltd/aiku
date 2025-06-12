<?php
/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-15h-38m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaProductTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';

    public function blueprint(): array
    {
        return match ($this) {
            RetinaProductTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
