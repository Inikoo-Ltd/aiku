<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:45:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterFamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE  = 'showcase';
    case FAMILIES     = 'families';
    case IMAGES    = 'images';
    case SALES     = 'sales';

    case HISTORY   = 'history';
    case VARIANTS   = 'variants';


    public function blueprint(): array
    {
        return match ($this) {

            MasterFamilyTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            MasterFamilyTabsEnum::FAMILIES => [
                'title' => __('Families in shop'),
                'icon'  => 'fal fa-store',
            ],

            MasterFamilyTabsEnum::IMAGES => [
                'title' => __('Media'),
                'icon'  => 'fal fa-camera-retro',
            ],
            MasterFamilyTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            MasterFamilyTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            MasterFamilyTabsEnum::VARIANTS => [
                 'title' => __('Variants'),
                 'icon'  => 'fal fa-tachometer-alt-fast',
             ],
        };
    }
}
