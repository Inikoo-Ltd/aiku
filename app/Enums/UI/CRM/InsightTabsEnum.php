<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:55 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum InsightTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DATA = 'data';
    case POOL_OPTIONS = 'pool_options';

    public function blueprint(): array
    {
        return match ($this) {
            InsightTabsEnum::DATA => [
                'title' => __('Data'),
                'icon' => 'fal fa-database',
            ],
            InsightTabsEnum::POOL_OPTIONS => [
                'title' => __('Pool options'),
                'icon' => 'fal fa-dollar-sign',
            ],
        };
    }
}
