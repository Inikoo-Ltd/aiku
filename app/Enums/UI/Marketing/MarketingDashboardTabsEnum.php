<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 14:31:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Marketing;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MarketingDashboardTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';
    case OFFERS = 'offers';
    case DATA_QUALITY = 'data_quality';

    public function blueprint(): array
    {
        return match ($this) {
            MarketingDashboardTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            MarketingDashboardTabsEnum::OFFERS => [
                'title' => __('Offer performance'),
                'icon'  => 'fal fa-badge-percent',
            ],
            MarketingDashboardTabsEnum::DATA_QUALITY => [
                'title' => __('Data quality'),
                'icon'  => 'fal fa-heartbeat',
            ],
        };
    }
}
