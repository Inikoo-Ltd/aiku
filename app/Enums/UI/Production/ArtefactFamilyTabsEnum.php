<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Production;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ArtefactFamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ARTEFACTS = 'artefacts';
    case HISTORY   = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            ArtefactFamilyTabsEnum::ARTEFACTS => [
                'title' => __('Artefacts'),
                'icon'  => 'fal fa-hamsa',
            ],
            ArtefactFamilyTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
            ],
        };
    }
}
