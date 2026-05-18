<?php

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Catalogue\ProductCategory;

enum IrisFamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case OVERVIEW = 'overview';
    case PRODUCTS = 'products';

    public function blueprint(ProductCategory $parent): array
    {
        $products = $parent->stats->number_products_state_active + $parent->stats->number_products_state_discontinuing;

        return match ($this) {
            IrisFamilyTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            IrisFamilyTabsEnum::PRODUCTS => [
                'title' => __('Products') . " ({$products})",
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
        };
    }
}
