<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:33:40 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WarehousesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case WAREHOUSES = 'warehouses';
    case WAREHOUSES_HISTORIES = 'warehouses_histories';

    public function blueprint(): array
    {
        return match ($this) {
            WarehousesTabsEnum::WAREHOUSES => [
                'title' => __('Warehouses'),
                'icon' => 'fal fa-warehouse-alt',
            ],
            WarehousesTabsEnum::WAREHOUSES_HISTORIES => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
