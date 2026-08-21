<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:16:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CustomerClientTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case HISTORY = 'history';


    public function blueprint(): array
    {
        return match ($this) {
            CustomerClientTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fas fa-info-circle',
            ],
            CustomerClientTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
