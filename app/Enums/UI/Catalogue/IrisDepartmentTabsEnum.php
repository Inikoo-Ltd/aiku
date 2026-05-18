<?php

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Catalogue\ProductCategory;

enum IrisDepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case OVERVIEW = 'overview';
    case SUB_DEPARTMENTS = 'sub_departments';
    case COLLECTIONS = 'collections';
    case FAMILIES = 'families';
    case PRODUCTS = 'products';

    public function blueprint(ProductCategory $parent): array
    {
        $subDepartments = $parent->stats->number_sub_departments_state_active + $parent->stats->number_sub_departments_state_discontinuing;
        $families       = $parent->stats->number_families_state_active + $parent->stats->number_families_state_discontinuing;
        $products       = $parent->stats->number_products_state_active + $parent->stats->number_products_state_discontinuing;
        $collections    = $parent->stats->number_collections_state_active;

        return match ($this) {
            IrisDepartmentTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            IrisDepartmentTabsEnum::SUB_DEPARTMENTS => [
                'title' => __('Sub departments') . " ({$subDepartments})",
                'icon'  => 'fal fa-dot-circle',
                'type'  => 'icon',
            ],
            IrisDepartmentTabsEnum::FAMILIES => [
                'title' => __('Families') . " ({$families})",
                'icon'  => 'fal fa-folder',
                'type'  => 'icon',
            ],
            IrisDepartmentTabsEnum::PRODUCTS => [
                'title' => __('Products') . " ({$products})",
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
            IrisDepartmentTabsEnum::COLLECTIONS => [
                'title' => __('Collections') . " ({$collections})",
                'icon'  => 'fal fa-album-collection',
                'type'  => 'icon',
            ],
        };
    }
}
