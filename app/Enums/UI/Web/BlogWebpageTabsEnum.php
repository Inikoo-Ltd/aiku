<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:14:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Web;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum BlogWebpageTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE             = 'showcase';
    case ANALYTICS            = 'analytics';
    case CHANGELOG            = 'changelog';
    case SNAPSHOTS            = 'snapshots';



    public function blueprint(): array
    {
        return match ($this) {
            BlogWebpageTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fas fa-info-circle',
            ],
            BlogWebpageTabsEnum::ANALYTICS => [
                'title' => __('Analytics'),
                'icon'  => 'fal fa-analytics',
            ],
            BlogWebpageTabsEnum::CHANGELOG => [
                'title' => __('Changelog'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            BlogWebpageTabsEnum::SNAPSHOTS => [
                'title' => __('Snapshots'),
                'icon'  => 'fal fa-layer-group',
            ],
        };
    }
}
