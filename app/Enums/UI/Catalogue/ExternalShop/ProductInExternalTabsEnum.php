<?php

/*
 * author Louis Perez
 * created on 29-01-2026-09h-36m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\UI\Catalogue\ExternalShop;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProductInExternalTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SALES = 'sales';


    case HISTORY = 'history';
    case STOCKS = 'stocks';
    case TRADE_UNITS = 'trade_units';
    case ORDERS = 'orders';
    case CUSTOMERS = 'customers';


    public function blueprint(): array
    {
        return match ($this) {
            ProductInExternalTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            ProductInExternalTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            ProductInExternalTabsEnum::ORDERS => [
                'title' => __('Orders'),
                'icon'  => 'fal fa-shopping-cart',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ProductInExternalTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],

            ProductInExternalTabsEnum::STOCKS => [
                'title' => __('SKUs'),
                'icon'  => 'fal fa-box',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ProductInExternalTabsEnum::TRADE_UNITS => [
                'title' => __('Trade units'),
                'icon'  => 'fal fa-atom',
                'type'  => 'icon',
                'align' => 'right',
            ],
            ProductInExternalTabsEnum::CUSTOMERS => [
                'title' => __('Customers'),
                'icon'  => 'fal fa-user',
            ],
        };
    }
}
