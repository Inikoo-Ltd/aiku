<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Nov 2025 09:58:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Discounts\OfferAllowance;

enum OfferAllowanceTargetFilter: string
{
    case ALL_PRODUCTS_IN_ORDER = 'all_products_in_order';

    public function label(): string
    {
        return match ($this) {
            OfferAllowanceTargetFilter::ALL_PRODUCTS_IN_ORDER => __('All products in order'),
        };
    }

    public function slug(): string
    {
        return match ($this) {
            OfferAllowanceTargetFilter::ALL_PRODUCTS_IN_ORDER => 'all',
        };
    }

}
