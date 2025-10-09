<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:18 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum StockFamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE = 'showcase';
    case SALES    = 'sales';
    case STOCKS   = 'stocks';
    case HISTORY  = 'history';
    case IMAGES   = 'images';
    case FEEDBACKS   = 'feedbacks';




    public function blueprint(): array
    {
        return match ($this) {
            StockFamilyTabsEnum::STOCKS => [
                'title' => __('Stocks'),
                'icon'  => 'fal fa-box',
            ],
            StockFamilyTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-dollar-sign',
            ],
            StockFamilyTabsEnum::FEEDBACKS => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Issues'),
                'icon'  => 'fal fa-poop',
            ],


            StockFamilyTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Changelog'),
                'icon'  => 'fal fa-clock',

            ],
            StockFamilyTabsEnum::IMAGES => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Images'),
                'icon'  => 'fal fa-camera-retro',

            ],
            StockFamilyTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
