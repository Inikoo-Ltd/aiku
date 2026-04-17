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
    case OUT_OF_STOCK = 'out_of_stock';
    case DORMANT_STOCK_1Y = 'dormant_stock_1y';
    case NOT_SOLD_1Y = 'not_sold_1y';

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
            ],
            OrganisationStockHistoryTabsEnum::OUT_OF_STOCK => [
                'title' => __('Out of Stock'),
                'icon'  => 'fal fa-empty-set',
            ],
            OrganisationStockHistoryTabsEnum::DORMANT_STOCK_1Y => [
                'title' => __('Dormant 1Y'),
                'icon'  => 'fal fa-skull-cow',
            ],
            OrganisationStockHistoryTabsEnum::NOT_SOLD_1Y => [
                'title' => __('Not Sold 1Y'),
                'icon'  => 'fal fa-ban',
            ],
        };
    }
}
