<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 20:44:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrganisationStockHistoryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ORG_STOCKS = 'org_stocks';
    case LOCATION_ORG_STOCKS = 'location_org_stocks';


    public function blueprint(): array
    {
        return match ($this) {
            OrganisationStockHistoryTabsEnum::ORG_STOCKS => [
                'title' => 'SKUs',
                'icon'  => 'fal fa-box',
            ],
            OrganisationStockHistoryTabsEnum::LOCATION_ORG_STOCKS => [
                'title' => __('Locations'),
                'icon'  => 'fal fa-inventory',
            ]
        };
    }
}
