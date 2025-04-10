<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Dec 2024 12:01:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\EmailOngoingRun;

use App\Enums\EnumHelperTrait;

enum EmailOngoingRunCodeEnum: string
{
    use EnumHelperTrait;


    case NEW_CUSTOMER = 'new_customer';
    case DELIVERY_NOTE_DISPATCHED = 'delivery_note_dispatched';
    case DELIVERY_NOTE_UNDISPATCHED = 'delivery_note_undispatched';
    case INVOICE_DELETED = 'invoice_deleted';
    case NEW_ORDER = 'new_order';
    case DELIVERY_CONFIRMATION = 'delivery_confirmation';
    case ORDER_CONFIRMATION = 'order_confirmation';
    case PASSWORD_REMINDER = 'password_reminder';

    case REGISTRATION = 'registration';
    case REGISTRATION_APPROVED = 'registration_approved';
    case REGISTRATION_REJECTED = 'registration_rejected';
    case RENTAL_AGREEMENT = 'rental_agreement';
    case PALLET_DELIVERY_PROCESSED = 'pallet_delivery_processed';
    case PALLET_RETURN_DISPATCHED = 'pallet_return_dispatched';

    case BASKET_LOW_STOCK = 'basket_low_stock';
    case BASKET_PUSH = 'basket_push';
    case NEW_CUSTOMER_PUSH = 'new_customer_push';
    case ABANDONED_CART = 'abandoned_cart';
    case REORDER_REMINDER = 'reorder_reminder';
    case OOS_NOTIFICATION = 'oos_notification';

    case SEND_INVOICE_TO_CUSTOMER = 'send_invoice_to_customer';

    case NEW_PALLET_RETURN_FROM_CUSTOMER = 'new_pallet_return_from_customer';
    case NEW_PALLET_DELIVERY_FROM_CUSTOMER = 'new_pallet_delivery_from_customer';
    case PALLET_DELIVERY_DELETED = 'pallet_delivery_deleted';
    case PALLET_RETURN_DELETED = 'pallet_return_deleted';
    case PALLET_DELETED = 'pallet_deleted';

}
