<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:50:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaSubDepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case FAMILIES = 'families';
    case PRODUCTS = 'products';
    case COLLECTIONS = 'collections';


    public function blueprint(): array
    {
        return match ($this) {
            RetinaSubDepartmentTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            RetinaSubDepartmentTabsEnum::FAMILIES => [
                'title' => __('families'),
                'icon'  => 'fal fa-folder',
                'type'  => 'icon',
            ],
            RetinaSubDepartmentTabsEnum::PRODUCTS => [
                'title' => __('products'),
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
            RetinaSubDepartmentTabsEnum::COLLECTIONS => [
                'title' => __('collections'),
                'icon'  => 'fal fa-album-collection',
                'type'  => 'icon',
            ],

        };
    }
}
