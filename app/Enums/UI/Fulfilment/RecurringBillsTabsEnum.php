<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 18:17:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RecurringBillsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case RECURRING_BILLS = 'recurring_bills';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            RecurringBillsTabsEnum::RECURRING_BILLS => [
                'title' => __('Recurring Bills'),
                'icon' => 'fal fa-bars',
            ],

            RecurringBillsTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ]
        };
    }
}
