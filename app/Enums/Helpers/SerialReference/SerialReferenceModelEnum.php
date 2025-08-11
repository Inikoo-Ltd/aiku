<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Apr 2023 12:27:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\SerialReference;

use App\Enums\EnumHelperTrait;

enum SerialReferenceModelEnum: string
{
    use EnumHelperTrait;


    case CUSTOMER = 'customer';
    case ORDER = 'order';
    case PURCHASE_ORDER = 'purchase_order';
    case STOCK_DELIVERY = 'stock_delivery';
    case PALLET_DELIVERY = 'pallet_delivery';
    case PALLET_RETURN = 'pallet_return';
    case PALLET = 'pallet';
    case PAYMENT = 'payment';
    case RENTAL_AGREEMENT = 'rental_agreement';
    case RECURRING_BILL = 'recurring_bill';
    case STORED_ITEM_AUDIT = 'stored_item_audit';
    case TOP_UP = 'top_up';
    case PURGE = 'purge';
    case INVOICE = 'invoice';
    case PICKING_SESSION = 'picking_session';

}
