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
    case TRADE_UNITS = 'trade_units';
    case IMAGES = 'images';
    case HISTORY  = 'history';


    public function blueprint(): array
    {
        return match ($this) {

            MasterAssetTabsEnum::TRADE_UNITS => [
                'title' => __('Trade units'),
                'icon'  => 'fal fa-atom',
                'type'  => 'icon',
                'align' => 'right',
            ],
            MasterAssetTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('changelog'),
                'icon'  => 'fal fa-clock',

            ],

            MasterAssetTabsEnum::IMAGES => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('media'),
                'icon'  => 'fal fa-camera-retro',
            ],
            MasterAssetTabsEnum::PRODUCTS => [
                'title' => __('products in shop'),
                'icon'  => 'fal fa-store',
            ],
            MasterAssetTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
