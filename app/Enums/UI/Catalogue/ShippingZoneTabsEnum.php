<?php

/*
 * author Arya Permana - Kirin
 * created on 18-10-2024-15h-09m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ShippingZoneTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE      = 'showcase';
    case HISTORY       = 'history';


    public function blueprint(): array
    {
        return match ($this) {

            ShippingZoneTabsEnum::SHOWCASE => [
                'title' => __('Details'),
                'icon'  => 'fal fa-shopping-cart',
            ],
            ShippingZoneTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
