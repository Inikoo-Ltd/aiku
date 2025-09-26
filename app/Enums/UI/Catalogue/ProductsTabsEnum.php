<?php

/*
 * author Arya Permana - Kirin
 * created on 20-12-2024-14h-19m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProductsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX   = 'index';
    case SALES      = 'sales';

    public function blueprint(): array
    {
        return match ($this) {
            ProductsTabsEnum::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            ProductsTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
        };
    }
}
