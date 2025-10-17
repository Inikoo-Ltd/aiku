<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Portfolio;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CustomerSalesChannelPortfolioTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case PRODUCTS = 'products';
    case LOGS     = 'logs';

    public function blueprint(): array
    {
        return match ($this) {
            CustomerSalesChannelPortfolioTabsEnum::PRODUCTS => [
                'title' => __('My Products'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            CustomerSalesChannelPortfolioTabsEnum::LOGS => [
                'title' => __('Logs'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ]
        };
    }
}
