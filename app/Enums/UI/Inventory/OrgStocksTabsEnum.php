<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgStocksTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX = 'index';
    case SALES = 'sales';

    public function blueprint(): array
    {
        return match ($this) {
            OrgStocksTabsEnum::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            OrgStocksTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
        };
    }
}
