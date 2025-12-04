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
    case IMAGES = 'images';
    case MASTER_PRODUCTS = 'master_products';
    case PRODUCTS = 'products';
    case STOCKS = 'stocks';
    case ORG_STOCKS = 'org_stocks';
    case HISTORY = 'history';
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
            TradeUnitTabsEnum::MASTER_PRODUCTS => [
                'title' => __('Master Products'),
                'icon'  => 'fab fa-octopus-deploy',
            ],
            TradeUnitTabsEnum::PRODUCTS => [
                'title' => __('Products'),
                'icon'  => 'fal fa-cube',
            ],
            TradeUnitTabsEnum::IMAGES => [
                'title' => __('Media'),
                'icon'  => 'fal fa-camera-retro',
            ],
            TradeUnitTabsEnum::STOCKS => [
                'title' => __('Master SKUs'),
                'icon'  => 'fal fa-cloud-rainbow',
            ],
            TradeUnitTabsEnum::ORG_STOCKS => [
                'title' => __('Org SKUs'),
                'icon'  => 'fal fa-box',
            ],
        };
    }
}
