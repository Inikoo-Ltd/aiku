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

    case WEBSITE_LAYOUT = 'website_layout';
    case DEPARTMENT     = 'department';
    case FAMILY         = 'family';
    case PRODUCT        = 'product';

    public function blueprint(): array
    {
        return match ($this) {
            WebsiteWorkshopTabsEnum::DEPARTMENT => [
                'title' => __('departments class'),
                'icon'  => 'fal fa-th',
            ],
            WebsiteWorkshopTabsEnum::FAMILY => [
                'title' => __('families class'),
                'icon'  => 'fal fa-th-large',
            ],
            WebsiteWorkshopTabsEnum::PRODUCT => [
                'title' => __('product'),
                'icon'  => 'fal fa-cube',
            ],
            WebsiteWorkshopTabsEnum::WEBSITE_LAYOUT => [
                'title' => __('layout'),
                'icon'  => 'fal fa-cheeseburger',
            ],
        };
    }
}
