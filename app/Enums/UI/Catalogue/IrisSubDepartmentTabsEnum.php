<?php

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Catalogue\ProductCategory;

enum IrisSubDepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case FAMILIES = 'families';
    case PRODUCTS = 'products';
    case COLLECTIONS = 'collections';

    public function blueprint(ProductCategory $parent): array
    {
        $families    = $parent->stats->number_families_state_active + $parent->stats->number_families_state_discontinuing;
        $products    = $parent->stats->number_products_state_active + $parent->stats->number_products_state_discontinuing;
        $collections = $parent->stats->number_collections_state_active;

        return match ($this) {
            IrisSubDepartmentTabsEnum::FAMILIES => [
                'title' => __('Families') . " ({$families})",
                'icon'  => 'fal fa-folder',
                'type'  => 'icon',
            ],
            IrisSubDepartmentTabsEnum::PRODUCTS => [
                'title' => __('Products') . " ({$products})",
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
            IrisSubDepartmentTabsEnum::COLLECTIONS => [
                'title' => __('Collections') . " ({$collections})",
                'icon'  => 'fal fa-album-collection',
                'type'  => 'icon',
            ],
        };
    }
}
