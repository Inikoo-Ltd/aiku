<?php

namespace App\Enums\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CatalogueTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE            = 'showcase';
    case TOP_LISTED_FAMILIES = 'top_listed_families';
    case TOP_LISTED_PRODUCTS = 'top_listed_products';
    case TOP_SOLD_PRODUCTS   = 'top_sold_products';
    case ON_ITS_WAY          = 'on_its_way';

    public function blueprint(): array
    {
        return match ($this) {
            self::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            self::TOP_LISTED_FAMILIES => [
                'title' => __('Top Listed Families'),
                'icon'  => 'fal fa-bars',
            ],
            self::TOP_LISTED_PRODUCTS => [
                'title' => __('Top Listed Products'),
                'icon'  => 'fal fa-list-alt',
            ],
            self::TOP_SOLD_PRODUCTS => [
                'title' => __('Top Sold Products'),
                'icon'  => 'fal fa-trophy',
            ],
            self::ON_ITS_WAY => [
                'title' => __('On its way'),
                'icon'  => 'fal fa-truck-container',
            ],
        };
    }
}
