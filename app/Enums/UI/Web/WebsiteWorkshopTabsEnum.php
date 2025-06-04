<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:14:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum WebsiteWorkshopTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case WEBSITE_LAYOUT     = 'website_layout';
    case SUB_DEPARTMENT     = 'sub_department';
    case FAMILY             = 'families';
    case PRODUCTS           = 'products';
    case PRODUCT            = 'product';

    public function blueprint(): array
    {
        return match ($this) {
            WebsiteWorkshopTabsEnum::SUB_DEPARTMENT => [
                'title' => __('sub-department block'),
                'icon'  => 'fal fa-th',
            ],
            WebsiteWorkshopTabsEnum::FAMILY => [
                'title' => __('families block'),
                'icon'  => 'fal fa-folder-tree',
            ],
            WebsiteWorkshopTabsEnum::PRODUCTS => [
                'title' => __('products block'),
                'icon'  => 'fal fa-th-large',
            ],
            WebsiteWorkshopTabsEnum::PRODUCT => [
                'title' => __('product page'),
                'icon'  => 'fal fa-cube',
            ],
            WebsiteWorkshopTabsEnum::WEBSITE_LAYOUT => [
                'title' => __('layout'),
                'icon'  => 'fal fa-cheeseburger',
            ],
        };
    }
}
