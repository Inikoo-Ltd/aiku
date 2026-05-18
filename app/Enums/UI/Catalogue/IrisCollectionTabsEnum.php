<?php

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Catalogue\Collection;

enum IrisCollectionTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;

    case OVERVIEW = 'overview';
    case FAMILIES = 'families';
    case PRODUCTS = 'products';

    public function blueprint(Collection $parent): array
    {
        $families = $parent->stats->number_families ?? 0;
        $products = $parent->stats->number_products ?? 0;

        return match ($this) {
            IrisCollectionTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            IrisCollectionTabsEnum::FAMILIES => [
                'title' => __('Families') . " ({$families})",
                'icon'  => 'fal fa-folder',
                'type'  => 'icon',
            ],
            IrisCollectionTabsEnum::PRODUCTS => [
                'title' => __('Products') . " ({$products})",
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
        };
    }
}
