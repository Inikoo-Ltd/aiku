<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 16:04:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Production;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ArtefactsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ARTEFACTS                       = 'artefacts';
    case ARTEFACTS_HISTORIES             = 'artefacts_histories';

    public function blueprint(): array
    {
        return match ($this) {
            ArtefactsTabsEnum::ARTEFACTS => [
                'title' => __('Artefacts'),
                'icon'  => 'fal fa-bars',
            ],
            ArtefactsTabsEnum::ARTEFACTS_HISTORIES => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
