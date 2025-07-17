<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:50:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaDepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SUB_DEPARTMENTS = 'sub_departments';
    case FAMILIES = 'families';
    case PRODUCTS = 'products';
    case COLLECTIONS = 'collections';


    public function blueprint(): array
    {
        return match ($this) {
            RetinaDepartmentTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            RetinaDepartmentTabsEnum::SUB_DEPARTMENTS => [
                'title' => __('sub departments'),
                'icon'  => 'fal fa-dot-circle',
                'type'  => 'icon',
            ],
            RetinaDepartmentTabsEnum::FAMILIES => [
                'title' => __('families'),
                'icon'  => 'fal fa-folder',
                'type'  => 'icon',
            ],
            RetinaDepartmentTabsEnum::PRODUCTS => [
                'title' => __('products'),
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
            RetinaDepartmentTabsEnum::COLLECTIONS => [
                'title' => __('collections'),
                'icon'  => 'fal fa-album-collection',
                'type'  => 'icon',
            ],

        };
    }
}
