<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:50:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum DepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SALES = 'sales';
    case OFFERS = 'offers';
    case RELATED_CATEGORIES = 'related_categories';

    case HISTORY = 'history';
    case DATA = 'data';
    case IMAGES = 'images';
    case CUSTOMERS = 'customers';

    public function blueprint(): array
    {
        return match ($this) {
            DepartmentTabsEnum::DATA => [
                'title' => __('Database'),
                'icon' => 'fal fa-database',
                'type' => 'icon',
                'align' => 'right',
            ],
            DepartmentTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon' => 'fal fa-money-bill-wave',
            ],
            DepartmentTabsEnum::CUSTOMERS => [
                'title' => __('Customers'),
                'icon' => 'fal fa-user',
                'type' => 'icon',
                'align' => 'right',
            ],
            DepartmentTabsEnum::OFFERS => [
                'title' => __('Offers'),
                'icon' => 'fal fa-tags',
            ],
            DepartmentTabsEnum::RELATED_CATEGORIES => [
                'title' => __('Related categories'),
                'icon' => 'fal fa-project-diagram',
            ],
            DepartmentTabsEnum::IMAGES => [
                'title' => __('Media'),
                'icon' => 'fal fa-camera-retro',
                'type' => 'icon',
                'align' => 'right',
            ],
            DepartmentTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            DepartmentTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon' => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
