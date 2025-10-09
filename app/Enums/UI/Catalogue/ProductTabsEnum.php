<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:49:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProductTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SALES = 'sales';
    // case VARIATIONS = 'variations';
    case HISTORY = 'history';
    case IMAGES = 'images';
    case STOCKS = 'stocks';
    case TRADE_UNITS = 'trade_units';
    case ORDERS = 'orders';
    case FAVOURITES = 'favourites';
    case REMINDERS = 'reminders';
    case ATTACHMENTS = 'attachments';



    public function blueprint(): array
    {
        return match ($this) {
            ProductTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            //            ProductTabsEnum::VARIATIONS => [
            //                'title' => __('Variations'),
            //                'icon'  => 'fal fa-stream',
            //            ],
            ProductTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            ProductTabsEnum::ORDERS => [
                'title' => __('Orders'),
                'icon'  => 'fal fa-shopping-cart',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ProductTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],

            ProductTabsEnum::STOCKS => [
                'title' => __('SKUs'),
                'icon'  => 'fal fa-box',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ProductTabsEnum::TRADE_UNITS => [
                'title' => __('Trade units'),
                'icon'  => 'fal fa-atom',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ProductTabsEnum::IMAGES => [
                'title' => __('Media'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ProductTabsEnum::REMINDERS => [
                'title' => __('Reminders'),
                'icon'  => 'fal fa-bell',
                'align' => 'right',
                'type'  => 'icon',
            ],
            ProductTabsEnum::FAVOURITES => [
                'title' => __('Favourites'),
                'icon'  => 'fal fa-heart',
                'align' => 'right',
                'type'  => 'icon',
            ],
            ProductTabsEnum::ATTACHMENTS => [
                'type'  => 'icon',
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
            ],
        };
    }
}
