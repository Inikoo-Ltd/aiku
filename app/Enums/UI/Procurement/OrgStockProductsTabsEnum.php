<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 21:35:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgStockProductsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SALES = 'sales';
    case PRODUCTS = 'products';

    public function blueprint(): array
    {
        return match ($this) {
            OrgStockProductsTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-dollar-sign',
            ],
            OrgStockProductsTabsEnum::PRODUCTS => [
                'title' => __('Products'),
                'icon'  => 'fal fa-cube',
            ],
        };
    }
}
