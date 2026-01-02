<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 22:02:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgStockProcurementTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case PURCHASE_ORDERS = 'purchase_orders';
    case SUPPLIERS_PRODUCTS = 'supplier_products';

    public function blueprint(): array
    {
        return match ($this) {
            OrgStockProcurementTabsEnum::PURCHASE_ORDERS => [
                'title' => __('Purchase orders'),
                'icon'  => 'fal fa-clipboard',
            ],
            OrgStockProcurementTabsEnum::SUPPLIERS_PRODUCTS => [
                'title' => __('Supplier product'),
                'icon'  => 'fal fa-hand-receiving',
            ],
        };
    }
}
