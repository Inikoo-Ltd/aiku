<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 May 2024 09:45:43 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ServicesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SERVICES = 'services';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            ServicesTabsEnum::SERVICES => [
                'title' => __('Services'),
                'icon' => 'fal fa-bars',
            ],

            ServicesTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ]
        };
    }
}
