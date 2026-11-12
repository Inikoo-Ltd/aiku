<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Marketing;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum AggregatedMarketingDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case OVERVIEW = 'overview';
    case INFO_WEBSITES = 'info_websites';

    public function blueprint(): array
    {
        return match ($this) {
            AggregatedMarketingDashboardTabsEnum::OVERVIEW => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            AggregatedMarketingDashboardTabsEnum::INFO_WEBSITES => [
                'title' => __('Info websites'),
                'icon'  => 'fal fa-globe',
            ],
        };
    }
}
