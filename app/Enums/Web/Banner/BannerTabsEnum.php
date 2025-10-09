<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Jul 2024 11:13:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Banner;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum BannerTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE              = 'showcase';
    case SNAPSHOTS             = 'snapshots';


    case CHANGELOG            = 'changelog';



    public function blueprint(): array
    {
        return match ($this) {
            BannerTabsEnum::SHOWCASE => [
                'title' => __('Banner'),
                'icon'  => 'fas fa-info-circle',
            ],
            BannerTabsEnum::SNAPSHOTS => [
                'title' => __('Snapshots'),
                'icon'  => 'fal fa-layer-group',
            ],

            BannerTabsEnum::CHANGELOG => [
                'title' => __('Changelog'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
