<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:49:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ServiceTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE   = 'showcase';
    // case VARIATIONS = 'variations';
    // case WEBPAGES   = 'webpages';
    case SALES      = 'sales';
    case ORDERS     = 'orders';
    case CUSTOMERS  = 'customers';
    case MAILSHOTS  = 'mailshots';

    case HISTORY = 'history';

    case DATA   = 'data';
    case IMAGES = 'images';
    case PARTS  = 'parts';



    public function blueprint(): array
    {
        return match ($this) {
            ServiceTabsEnum::SHOWCASE => [
                'title' => __('Product'),
                'icon'  => 'fas fa-info-circle',
            ],
            // ServiceTabsEnum::VARIATIONS => [
            //     'title' => __('Variations'),
            //     'icon'  => 'fal fa-stream',
            // ],
            // ServiceTabsEnum::WEBPAGES => [
            //     'title' => __('Webpages'),
            //     'icon'  => 'fal fa-globe',
            // ],
            ServiceTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            ServiceTabsEnum::ORDERS => [
                'title' => __('Orders'),
                'icon'  => 'fal fa-shopping-cart',
            ],
            ServiceTabsEnum::CUSTOMERS => [
                'title' => __('Customers'),
                'icon'  => 'fal fa-users',

            ],
            ServiceTabsEnum::MAILSHOTS => [
                'title' => __('Mailshots'),
                'icon'  => 'fal fa-bullhorn',

            ],


            ServiceTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ServiceTabsEnum::DATA => [
                'title' => __('Data'),
                'icon'  => 'fal fa-database',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ServiceTabsEnum::PARTS => [
                'title' => __('Parts'),
                'icon'  => 'fal fa-box',
                'type'  => 'icon',
                'align' => 'right',
            ],
            // ServiceTabsEnum::SERVICE => [
            //     'title' => __('Service'),
            //     'icon'  => 'fal fa-box',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
            // ServiceTabsEnum::RENTAL => [
            //     'title' => __('Rental'),
            //     'icon'  => 'fal fa-box',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],

            ServiceTabsEnum::IMAGES => [
                'title' => __('Images'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon',
                'align' => 'right',
            ]
        };
    }
}
