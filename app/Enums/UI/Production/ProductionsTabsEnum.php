<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:33:40 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Production;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProductionsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case PRODUCTIONS = 'productions';
    case PRODUCTIONS_HISTORIES = 'productions_histories';

    public function blueprint(): array
    {
        return match ($this) {
            ProductionsTabsEnum::PRODUCTIONS => [
                'title' => __('Factories'),
                'icon' => 'fal fa-industry',
            ],
            ProductionsTabsEnum::PRODUCTIONS_HISTORIES => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
