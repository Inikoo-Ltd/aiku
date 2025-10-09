<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:24:19 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Discounts;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OfferTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case VOUCHERS            = 'vouchers';
    case ORDERS              = 'orders';
    case CUSTOMERS           = 'customers';

    case SETTINGS            = 'setting';
    case CHANGELOG           = 'changelog';


    public function blueprint(): array
    {
        return match ($this) {
            OfferTabsEnum::VOUCHERS => [
                'title' => __('Vouchers'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            OfferTabsEnum::ORDERS => [
                'title' => __('Orders'),
                'icon'  => 'fal fa-shopping-cart',
            ],
            OfferTabsEnum::CUSTOMERS => [
                'title' => __('Customers'),
                'icon'  => 'fal fa-users',
            ],
            OfferTabsEnum::SETTINGS => [
                'title' => __('Settings'),
                'icon'  => 'fal fa-slider-h',
                'type'  => 'icon-only',
            ],OfferTabsEnum::CHANGELOG => [
                'title' => __('Changelog'),
                'icon'  => 'fal fa-road',
                'type'  => 'icon-only',
            ],
        };
    }
}
