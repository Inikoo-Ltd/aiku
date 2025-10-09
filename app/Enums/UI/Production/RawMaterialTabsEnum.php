<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:20:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Production;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RawMaterialTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE            = 'showcase';
    case HISTORY             = 'history';



    public function blueprint(): array
    {
        return match ($this) {
            RawMaterialTabsEnum::HISTORY     => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Changelog'),
                'icon'  => 'fal fa-clock',
            ],
            RawMaterialTabsEnum::SHOWCASE => [
                'title' => __('Details'),
                'icon'  => 'fal fa-chart-network',
            ],
        };
    }
}
