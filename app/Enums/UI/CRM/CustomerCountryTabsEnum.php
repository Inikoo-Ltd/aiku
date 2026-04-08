<?php

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CustomerCountryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE          = 'showcase';
    case TOP_PRODUCTS      = 'top_products';
    case SEASONAL_PRODUCTS = 'seasonal_products';

    public function blueprint(): array
    {
        return match ($this) {
            CustomerCountryTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            CustomerCountryTabsEnum::TOP_PRODUCTS => [
                'title' => __('Top Products'),
                'icon'  => 'fal fa-star',
            ],
            CustomerCountryTabsEnum::SEASONAL_PRODUCTS => [
                'title' => __('Seasonal'),
                'icon'  => 'fal fa-calendar-alt',
            ],
        };
    }
}
