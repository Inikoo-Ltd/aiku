<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 15:17:32 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Fulfilment\Pallet;

enum PalletTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case SHOWCASE     = 'showcase';
    case STORED_ITEMS = 'stored_items';
    case MOVEMENTS      = 'movements';
    case HISTORY      = 'history';

    public function blueprint(Pallet $pallet): array
    {
        return match ($this) {
            PalletTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fas fa-info-circle',
            ],
            PalletTabsEnum::STORED_ITEMS => [
                'title' => __("Customer's SKUs"). " ({$pallet->number_stored_items})",
                'icon'  => 'fal fa-narwhal',
            ],
            PalletTabsEnum::MOVEMENTS => [
                'title' => __('SKU movements'),
                'icon'  => 'fal fa-exchange',
            ],
            PalletTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ]
        };
    }
}
