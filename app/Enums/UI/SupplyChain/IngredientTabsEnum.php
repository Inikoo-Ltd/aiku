<?php

/*
 * author Arya Permana - Kirin
 * created on 04-12-2024-14h-54m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum IngredientTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';

    public function blueprint(): array
    {
        return match ($this) {
            IngredientTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon' => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
