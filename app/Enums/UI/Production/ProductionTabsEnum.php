<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:20:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Production;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProductionTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE            = 'showcase';
    case HISTORY             = 'history';



    public function blueprint(): array
    {
        return match ($this) {
            ProductionTabsEnum::HISTORY     => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Changelog'),
                'icon'  => 'fal fa-clock',
            ],
            ProductionTabsEnum::SHOWCASE => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-chart-network',
            ],
        };
    }
}
