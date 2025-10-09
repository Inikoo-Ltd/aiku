<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-13h-33m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterShopTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SALES = 'sales';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            MasterShopTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            MasterShopTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            MasterShopTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
