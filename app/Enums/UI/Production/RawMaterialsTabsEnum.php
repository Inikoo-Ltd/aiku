<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 May 2024 10:23:50 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Production;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RawMaterialsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case RAW_MATERIALS                       = 'raw_materials';
    case RAW_MATERIALS_HISTORIES             = 'raw_materials_histories';

    public function blueprint(): array
    {
        return match ($this) {
            RawMaterialsTabsEnum::RAW_MATERIALS => [
                'title' => __('Raw materials'),
                'icon'  => 'fal fa-bars',
            ],
            RawMaterialsTabsEnum::RAW_MATERIALS_HISTORIES => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
