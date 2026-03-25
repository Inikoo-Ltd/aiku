<?php

namespace App\Enums\UI\Goods;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TradeUnitFamiliesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX = 'index';
    case SALES = 'sales';

    public function blueprint(): array
    {
        return match ($this) {
            TradeUnitFamiliesTabsEnum::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            TradeUnitFamiliesTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
        };
    }
}
