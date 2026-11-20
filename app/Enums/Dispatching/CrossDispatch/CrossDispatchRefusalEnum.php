<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\CrossDispatch;

use App\Enums\EnumHelperTrait;

enum CrossDispatchRefusalEnum: string
{
    use EnumHelperTrait;

    case SAME_ORGANISATION = 'same_organisation';
    case NOT_ENABLED = 'not_enabled';
    case NOT_DROPSHIPPING = 'not_dropshipping';
    case ORDER_STATE = 'order_state';
    case NOTHING_TO_DISPATCH = 'nothing_to_dispatch';
    case TIKTOK = 'tiktok';
    case CASH_ON_DELIVERY = 'cash_on_delivery';
    case COLLECTION = 'collection';
    case PRE_ORDER = 'pre_order';
    case LOCKED_SHIPPER = 'locked_shipper';
    case REPLACEMENT = 'replacement';
    case PICKED = 'picked';
    case CUSTOMS_CROSSING = 'customs_crossing';
    case FILL_TO_ORDER = 'fill_to_order';
    case PRODUCT_WITHOUT_SKO = 'product_without_sko';
    case SKO_MISSING = 'sko_missing';
    case SKO_DUPLICATE = 'sko_duplicate';
    case SKO_INACTIVE = 'sko_inactive';
    case SKO_CONTENT_DIFFERS = 'sko_content_differs';
    case SKO_WITHOUT_COMMERCIAL_VALUE = 'sko_without_commercial_value';
    case NOT_ENOUGH_STOCK = 'not_enough_stock';
}
