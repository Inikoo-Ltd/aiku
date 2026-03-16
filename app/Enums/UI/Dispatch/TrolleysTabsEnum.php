<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 13:59:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Dispatch;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum TrolleysTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case TROLLEYS                       = 'trolleys';
    case TROLLEYS_HISTORIES             = 'trolleys_histories';

    public function blueprint(): array
    {
        return match ($this) {
            TrolleysTabsEnum::TROLLEYS => [
                'title' => __('Trolleys'),
                'icon'  => 'fal fa-dolly-flatbed-alt',
            ],
            TrolleysTabsEnum::TROLLEYS_HISTORIES => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
