<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Feb 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterGoldRewardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case WITH     = 'with';
    case WITHOUT  = 'without';

    public function blueprint(): array
    {
        return match ($this) {
            MasterGoldRewardTabsEnum::WITH => [
                'title' => __('With GR'),
                'icon'  => 'fal fa-check-circle',
            ],
            MasterGoldRewardTabsEnum::WITHOUT => [
                'title' => __('Without GR'),
                'icon'  => 'fal fa-times-circle',
            ]
        };
    }
}
