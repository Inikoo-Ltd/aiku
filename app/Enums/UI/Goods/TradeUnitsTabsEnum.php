<?php

namespace App\Enums\UI\Goods;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TradeUnitsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX = 'index';
    case SALES = 'sales';

    public function blueprint(): array
    {
        return match ($this) {
            TradeUnitsTabsEnum::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            TradeUnitsTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
        };
    }
}
