<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:50:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Enums\HasTabsWithQuantity;
use App\Models\Catalogue\ProductCategory;

enum RetinaFamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case SHOWCASE = 'showcase';
    case PRODUCTS = 'products';


    public function blueprint(ProductCategory $parent): array
    {
        $products = $parent->stats->number_products_state_active + $parent->stats->number_products_state_discontinuing;

        return match ($this) {
            RetinaFamilyTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            RetinaFamilyTabsEnum::PRODUCTS => [
                'title' => __('products'). " ({$products})",
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
        };
    }
}
