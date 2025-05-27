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

    case HISTORY = 'history';
    case IMAGES = 'images';
    case ATTACHMENTS = 'attachments';
    case FEEDBACKS = 'feedbacks';


    public function blueprint(): array
    {
        return match ($this) {
            StockTabsEnum::SALES => [
                'title' => __('sales'),
                'icon'  => 'fal fa-dollar-sign',
            ],

            StockTabsEnum::FEEDBACKS => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('issues'),
                'icon'  => 'fal fa-poop',
            ],

            StockTabsEnum::ATTACHMENTS => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('attachments'),
                'icon'  => 'fal fa-paperclip',

            ],
            StockTabsEnum::IMAGES => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('images'),
                'icon'  => 'fal fa-camera-retro',
            ],
            StockTabsEnum::HISTORY => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('history'),
                'icon'  => 'fal fa-clock',

            ],
            StockTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],

            StockTabsEnum::TRADE_UNITS => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('trade units'),
                'icon'  => 'fal fa-cubes',
            ],
        };
    }
}
