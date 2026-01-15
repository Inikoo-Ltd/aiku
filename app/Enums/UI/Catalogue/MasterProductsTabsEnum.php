<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterProductsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX     = 'index';
    case SALES     = 'sales';

    public function blueprint(): array
    {
        return match ($this) {
            MasterProductsTabsEnum::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            MasterProductsTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ]
        };
    }
}
