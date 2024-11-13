<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:32:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WarehouseTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE        = 'showcase';
    case DASHBOARD       = 'dashboard';
    case HISTORY         = 'history';



    public function blueprint(): array
    {
        return match ($this) {
            WarehouseTabsEnum::DASHBOARD => [
                'title' => __('stats'),
                'icon'  => 'fal fa-chart-line',
            ],
            WarehouseTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
            ],
            WarehouseTabsEnum::SHOWCASE => [
                'title' => __('warehouse'),
                'icon'  => 'fas fa-info-circle',
            ],
        };
    }
}
