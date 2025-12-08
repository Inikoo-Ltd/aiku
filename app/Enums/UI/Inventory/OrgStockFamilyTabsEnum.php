<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Aug 2024 10:14:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgStockFamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case ORG_STOCKS = 'org_stocks';
    case HISTORY = 'history';
    case IMAGES = 'images';

    public function blueprint(): array
    {
        return match ($this) {
            OrgStockFamilyTabsEnum::ORG_STOCKS => [
                'title' => __('Stocks'),
                'icon' => 'fal fa-box',
            ],

            OrgStockFamilyTabsEnum::HISTORY => [
                'align' => 'right',
                'title' => __('Changelog'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
            ],
            OrgStockFamilyTabsEnum::IMAGES => [
                'align' => 'right',
                'title' => __('Images'),
                'icon' => 'fal fa-camera-retro',
                'type' => 'icon',
            ],
            OrgStockFamilyTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon' => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
