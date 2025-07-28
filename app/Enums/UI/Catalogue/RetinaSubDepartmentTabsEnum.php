<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:50:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Catalogue\ProductCategory;

enum RetinaSubDepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case SHOWCASE = 'showcase';
    case FAMILIES = 'families';
    case PRODUCTS = 'products';
    case COLLECTIONS = 'collections';


    public function blueprint(ProductCategory $parent): array
    {
        $families = $parent->stats->number_families_state_active + $parent->stats->number_families_state_discontinuing;
        $products = $parent->stats->number_products_state_active + $parent->stats->number_products_state_discontinuing;
        $collection = $parent->stats->number_collections_state_active;

        return match ($this) {
            RetinaSubDepartmentTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            RetinaSubDepartmentTabsEnum::FAMILIES => [
                'title' => __('families'). " ({$families})",
                'icon'  => 'fal fa-folder',
                'type'  => 'icon',
            ],
            RetinaSubDepartmentTabsEnum::PRODUCTS => [
                'title' => __('products'). " ({$products})",
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
            RetinaSubDepartmentTabsEnum::COLLECTIONS => [
                'title' => __('collections'). " ({$collection})",
                'icon'  => 'fal fa-album-collection',
                'type'  => 'icon',
            ],

        };
    }
}
