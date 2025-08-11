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

enum RetinaDepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case SHOWCASE = 'showcase';
    case SUB_DEPARTMENTS = 'sub_departments';
    case COLLECTIONS = 'collections';
    case FAMILIES = 'families';
    case PRODUCTS = 'products';


    public function blueprint(ProductCategory $parent): array
    {
        $subDepartments = $parent->stats->number_sub_departments_state_active + $parent->stats->number_sub_departments_state_discontinuing;
        $families = $parent->stats->number_families_state_active + $parent->stats->number_families_state_discontinuing;
        $products = $parent->stats->number_products_state_active + $parent->stats->number_products_state_discontinuing;
        $collection = $parent->stats->number_collections_state_active;

        return match ($this) {
            RetinaDepartmentTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            RetinaDepartmentTabsEnum::SUB_DEPARTMENTS => [
                'title' => __('sub departments'). " ({$subDepartments})",
                'icon'  => 'fal fa-dot-circle',
                'type'  => 'icon',
            ],
            RetinaDepartmentTabsEnum::FAMILIES => [
                'title' => __('families'). " ({$families})",
                'icon'  => 'fal fa-folder',
                'type'  => 'icon',
            ],
            RetinaDepartmentTabsEnum::PRODUCTS => [
                'title' => __('products'). " ({$products})",
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
            RetinaDepartmentTabsEnum::COLLECTIONS => [
                'title' => __('collections'). " ({$collection})",
                'icon'  => 'fal fa-album-collection',
                'type'  => 'icon',
            ],

        };
    }
}
