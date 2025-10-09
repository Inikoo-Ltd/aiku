<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:13:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProspectsQueriesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case LISTS     = 'lists';

    case HISTORY   = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            ProspectsQueriesTabsEnum::LISTS => [
                'title' => __('Lists'),
                'icon'  => 'fal fa-code-branch',
            ],
            ProspectsQueriesTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ]
        };
    }
}
