<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:16:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum StockTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE = 'showcase';
    case SALES = 'sales';

    case TRADE_UNITS = 'trade_units';
    case ORG_STOCKS = 'org_stocks';


    case HISTORY = 'history';
    case IMAGES = 'images';
    case ATTACHMENTS = 'attachments';
    case FEEDBACKS = 'feedbacks';


    public function blueprint(): array
    {
        return match ($this) {
            StockTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-dollar-sign',
            ],

            StockTabsEnum::FEEDBACKS => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Issues'),
                'icon'  => 'fal fa-poop',
            ],

            StockTabsEnum::ATTACHMENTS => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',

            ],
            StockTabsEnum::IMAGES => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('Images'),
                'icon'  => 'fal fa-camera-retro',
            ],
            StockTabsEnum::HISTORY => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('History'),
                'icon'  => 'fal fa-clock',

            ],
            StockTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],

            StockTabsEnum::TRADE_UNITS => [
                'title' => __('Trade units'),
                'icon'  => 'fal fa-atom',
            ],
            StockTabsEnum::ORG_STOCKS => [
                'title' => __('Org SKUs'),
                'icon'  => 'fal fa-box',
            ],
        };
    }
}
