<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:55 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Dashboard;

use App\Enums\EnumHelperTrait;

enum DashboardDeltaIconType: string
{
    use EnumHelperTrait;


    case DECREASE = 'decrease';
    case INCREASE = 'increase';
    case NO_CHANGE = 'neutral';

    public function icon(): array
    {
        return match ($this) {
            DashboardDeltaIconType::DECREASE => [
                'change' => 'decrease',
                'state' => 'negative',
            ],
            DashboardDeltaIconType::INCREASE => [
                'change' => 'increase',
                'state' => 'positive',
            ],
            DashboardDeltaIconType::NO_CHANGE => [
                'change' => 'no_change',
                'state' => 'neutral',
            ],
        };
    }

    public function iconInverse(): array
    {
        return match ($this) {
            DashboardDeltaIconType::DECREASE => [
                'change' => 'increase',
                'state' => 'negative',
            ],
            DashboardDeltaIconType::INCREASE => [
                'change' => 'decrease',
                'state' => 'positive',
            ],
            DashboardDeltaIconType::NO_CHANGE => [
                'change' => 'no_change',
                'state' => 'neutral',
            ],
        };
    }

}
