<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterAssetTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case PRODUCTS = 'products';
    case IMAGES = 'images';
    case SALES = 'sales';

    case HISTORY  = 'history';
    case TRADE_UNITS = 'trade_units';


    public function blueprint(): array
    {
        return match ($this) {
            MasterAssetTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            MasterAssetTabsEnum::PRODUCTS => [
                'title' => __('Products in shop'),
                'icon'  => 'fal fa-store',
            ],
            MasterAssetTabsEnum::IMAGES => [
                'title' => __('Media'),
                'icon'  => 'fal fa-camera-retro',
            ],
            MasterAssetTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            
            MasterAssetTabsEnum::TRADE_UNITS => [
                'title' => __('Trade units'),
                'icon'  => 'fal fa-atom',
                'type'  => 'icon',
                'align' => 'right',
            ],
            MasterAssetTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'align' => 'right',
                'type'  => 'icon',
            ],
        };
    }
}
