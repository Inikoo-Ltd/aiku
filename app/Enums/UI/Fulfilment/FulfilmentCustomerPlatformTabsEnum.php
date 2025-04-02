<?php
/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-15h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FulfilmentCustomerPlatformTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE      = 'showcase';
    case AGREED_PRICES = 'agreed_prices';

    public function blueprint(): array
    {
        return match ($this) {
            FulfilmentCustomerTabsEnum::SHOWCASE => [
                'title' => __('showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
        };
    }
}
