<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:50:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaFamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case PRODUCTS = 'products';


    public function blueprint(): array
    {
        return match ($this) {
            RetinaFamilyTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            RetinaFamilyTabsEnum::PRODUCTS => [
                'title' => __('products'),
                'icon'  => 'fal fa-cube',
                'type'  => 'icon',
            ],
        };
    }
}
