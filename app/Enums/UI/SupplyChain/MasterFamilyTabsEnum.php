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
    case SALES     = 'sales';



    case HISTORY   = 'history';
    case IMAGES    = 'images';


    public function blueprint(): array
    {
        return match ($this) {

            MasterFamilyTabsEnum::SALES => [
                'title' => __('sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            MasterFamilyTabsEnum::FAMILIES => [
                'title' => __('families in shop'),
                'icon'  => 'fal fa-store',
            ],

            MasterFamilyTabsEnum::IMAGES => [
                'title' => __('media'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon',
                'align' => 'right',
            ],
            MasterFamilyTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            MasterFamilyTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
