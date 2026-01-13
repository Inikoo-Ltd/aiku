<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterCollectionsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX     = 'index';
    case SALES     = 'sales';

    public function blueprint(): array
    {
        return match ($this) {
            MasterCollectionsTabsEnum::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            MasterCollectionsTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ]
        };
    }
}
