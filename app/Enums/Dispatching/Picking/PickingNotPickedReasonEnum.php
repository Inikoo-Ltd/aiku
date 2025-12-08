<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 05:07:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\Picking;

use App\Enums\EnumHelperTrait;

enum PickingNotPickedReasonEnum: string
{
    use EnumHelperTrait;

    case NA = 'not-applicable';
    case OUT_OF_STOCK = 'out-of-stock';
    case CANCELLED_BY_CUSTOMER = 'cancelled-by-customer';
    case CANCELLED_BY_WAREHOUSE = 'cancelled-by-warehouse';

    public static function labels($forElements = false): array
    {
        return [
            'not-applicable' => __('Not Applicable'),
            'out-of-stock' => __('Out of Stock'),
            'cancelled-by-customer' => __('Cancelled by Customer'),
            'cancelled-by-warehouse' => __('Cancelled by Warehouse'),
        ];
    }
}
