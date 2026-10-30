<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Production;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum DevelopmentRoomTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case EXPERIMENTS = 'experiments';
    case TRIAL_RUNS  = 'trial_runs';
    case LAUNCHED    = 'launched';
    case CANCELLED   = 'cancelled';
    case MERGED      = 'merged';

    public function blueprint(): array
    {
        return match ($this) {
            DevelopmentRoomTabsEnum::EXPERIMENTS => [
                'title' => __('Experiments'),
                'icon'  => 'fal fa-vial',
            ],
            DevelopmentRoomTabsEnum::TRIAL_RUNS => [
                'title' => __('Trial runs'),
                'icon'  => 'fal fa-flask',
            ],
            DevelopmentRoomTabsEnum::LAUNCHED => [
                'title' => __('Launched'),
                'icon'  => 'fal fa-rocket-launch',
            ],
            DevelopmentRoomTabsEnum::CANCELLED => [
                'title' => __('Cancelled'),
                'icon'  => 'fal fa-ban',
            ],
            DevelopmentRoomTabsEnum::MERGED => [
                'title' => __('Merged back'),
                'icon'  => 'fal fa-code-merge',
            ],
        };
    }
}
