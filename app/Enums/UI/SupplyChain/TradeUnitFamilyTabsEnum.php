<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:16:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TradeUnitFamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE = 'showcase';
    case TRADE_UNITS = 'trade_units';


    public function blueprint(): array
    {
        return match ($this) {
            TradeUnitFamilyTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            TradeUnitFamilyTabsEnum::TRADE_UNITS => [
                'title' => __('trade units'),
                'icon'  => 'fal fa-atom',
            ],

        };
    }
}
