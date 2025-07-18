<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:50:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Catalogue\Collection;

enum RetinaCollectionTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;



    case SHOWCASE            = 'showcase';
    case FAMILIES            = 'families';
    case PRODUCTS            = 'products';
    case COLLECTIONS         = 'collections';


    public function blueprint(Collection $parent): array
    {
        return match ($this) {
            RetinaCollectionTabsEnum::SHOWCASE => [
                'title' => __('Details'),
                'icon'  => 'fas fa-info-circle',
            ],
            RetinaCollectionTabsEnum::FAMILIES => [
                'title' => __('Families')." ({$parent->stats->number_families})",
                'icon'  => 'fal fa-folder',
            ],
            RetinaCollectionTabsEnum::PRODUCTS => [
                'title' => __('Products')." ({$parent->stats->number_products})",
                'icon'  => 'fal fa-cube',
            ],
            RetinaCollectionTabsEnum::COLLECTIONS => [
                'title' => __('Collections')." ({$parent->stats->number_collections})",
                'icon'  => 'fal fa-cube',
            ],
        };
    }
}
