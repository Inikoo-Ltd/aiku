<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 14:31:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Group;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum GrpTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE     = 'showcase';
    case HISTORY      = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            GrpTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            GrpTabsEnum::SHOWCASE => [
                'title' => __('Group'),
                'icon'  => 'fas fa-info-circle',
            ],
        };
    }
}
