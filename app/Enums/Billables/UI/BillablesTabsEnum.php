<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 22:42:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Billables\UI;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum BillablesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';

    public function blueprint(): array
    {
        return match ($this) {
            BillablesTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon' => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
