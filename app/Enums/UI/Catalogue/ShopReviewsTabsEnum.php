<?php

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ShopReviewsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case PRODUCTS = 'products';
    case FAMILIES = 'families';
    case SHOP = 'shop';

    public function blueprint(): array
    {
        return match ($this) {
            ShopReviewsTabsEnum::PRODUCTS => [
                'title' => __('Products'),
                'icon'  => 'fal fa-cube',
            ],
            ShopReviewsTabsEnum::FAMILIES => [
                'title' => __('Families'),
                'icon'  => 'fal fa-folder-tree',
            ],
            ShopReviewsTabsEnum::SHOP => [
                'title' => __('Shop'),
                'icon'  => 'fal fa-store-alt',
            ],
        };
    }

    public function scope(): string
    {
        return match ($this) {
            ShopReviewsTabsEnum::PRODUCTS => 'product',
            ShopReviewsTabsEnum::FAMILIES => 'family',
            ShopReviewsTabsEnum::SHOP     => 'overall',
        };
    }
}
