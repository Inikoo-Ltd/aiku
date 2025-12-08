<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 May 2024 10:30:12 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PhysicalGoodsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case PHYSICAL_GOODS = 'physical_goods';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {

            PhysicalGoodsTabsEnum::PHYSICAL_GOODS => [
                'title' => __('Goods'),
                'icon' => 'fal fa-bars',
            ],

            PhysicalGoodsTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ]
        };
    }
}
