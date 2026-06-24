<?php

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ShopReviewsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOP = 'shop';
    case FAMILIES = 'families';
    case PRODUCTS = 'products';

    public function blueprint(): array
    {
        return match ($this) {
            ShopReviewsTabsEnum::SHOP => [
                'title' => __('Shop'),
                'icon'  => 'fal fa-store-alt',
            ],
            ShopReviewsTabsEnum::FAMILIES => [
                'title' => __('Families'),
                'icon'  => 'fal fa-folder-tree',
            ],
            ShopReviewsTabsEnum::PRODUCTS => [
                'title' => __('Products'),
                'icon'  => 'fal fa-cube',
            ],
        };
    }

    public function scope(): string
    {
        return match ($this) {
            ShopReviewsTabsEnum::SHOP     => 'overall',
            ShopReviewsTabsEnum::FAMILIES => 'family',
            ShopReviewsTabsEnum::PRODUCTS => 'product',
        };
    }
}
