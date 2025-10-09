<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:16:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TradeUnitTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE = 'showcase';
    case PRODUCTS = 'products';
    case STOCKS = 'stocks';
    case HISTORY = 'history';
    case IMAGES = 'images';
    case ATTACHMENTS = 'attachments';
    case FEEDBACKS = 'feedbacks';


    public function blueprint(): array
    {
        return match ($this) {
            TradeUnitTabsEnum::FEEDBACKS => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Issues'),
                'icon'  => 'fal fa-poop',
            ],

            TradeUnitTabsEnum::ATTACHMENTS => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',

            ],
            TradeUnitTabsEnum::IMAGES => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('Media'),
                'icon'  => 'fal fa-camera-retro',
            ],
            TradeUnitTabsEnum::HISTORY => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('History'),
                'icon'  => 'fal fa-clock',

            ],
            TradeUnitTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            TradeUnitTabsEnum::PRODUCTS => [
                'title' => __('Products'),
                'icon'  => 'fal fa-cube',
            ],
            TradeUnitTabsEnum::STOCKS => [
                'title' => __('SKUs'),
                'icon'  => 'fal fa-box',
            ],

        };
    }
}
