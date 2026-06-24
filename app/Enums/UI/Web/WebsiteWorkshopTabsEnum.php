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

    case WEBSITE_LAYOUT             = 'website_layout';
    case DEPARTMENT_DESCRIPTION     = 'department_description';
    case SUB_DEPARTMENT             = 'sub_department';
    case FAMILY                     = 'families';
    case FAMILIES_OVERVIEW          = 'families_overview';
    case FAMILIES_DESCRIPTION       = 'families_description';
    case PRODUCTS                   = 'products';
    case PRODUCT                    = 'product';

    case HISTORY                    = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            WebsiteWorkshopTabsEnum::DEPARTMENT_DESCRIPTION => [
                'title' => __('Department description block'),
                'icon'  => 'fal fa-page-break',
            ],
            WebsiteWorkshopTabsEnum::SUB_DEPARTMENT => [
                'title' => __('Sub-department block'),
                'icon'  => 'fal fa-th',
            ],
            WebsiteWorkshopTabsEnum::FAMILY => [
                'title' => __('Families block'),
                'icon'  => 'fal fa-folder-tree',
            ],
            WebsiteWorkshopTabsEnum::FAMILIES_OVERVIEW => [
                'title' => __('Families overview block'),
                'icon'  => 'fal fa-folder-tree',
            ],
            WebsiteWorkshopTabsEnum::FAMILIES_DESCRIPTION => [
                'title' => __('Families description block'),
                'icon'  => 'fal fa-page-break',
            ],
            WebsiteWorkshopTabsEnum::PRODUCTS => [
                'title' => __('Products block'),
                'icon'  => 'fal fa-th-large',
            ],
            WebsiteWorkshopTabsEnum::PRODUCT => [
                'title' => __('Product page'),
                'icon'  => 'fal fa-cube',
            ],
            WebsiteWorkshopTabsEnum::WEBSITE_LAYOUT => [
                'title' => __('Layout'),
                'icon'  => 'fal fa-cheeseburger',
            ],
            WebsiteWorkshopTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
