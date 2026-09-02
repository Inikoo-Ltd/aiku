<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Sept 2026 10:12:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Order;

use App\Enums\EnumHelperTrait;

enum OrderCancellationReasonEnum: string
{
    use EnumHelperTrait;

    case OUT_OF_STOCK = 'out_of_stock';
    case CUSTOMER_REQUEST = 'customer_request';
    case DUPLICATE_ORDER = 'duplicate_order';
    case PAYMENT_ISSUE = 'payment_issue';
    case DELIVERY_ISSUE = 'delivery_issue';
    case ORDER_PLACED_IN_ERROR = 'order_placed_in_error';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OUT_OF_STOCK => __('Item out of stock'),
            self::CUSTOMER_REQUEST => __('Customer requested cancellation'),
            self::DUPLICATE_ORDER => __('Duplicate order'),
            self::PAYMENT_ISSUE => __('Payment issue'),
            self::DELIVERY_ISSUE => __('Delivery or address issue'),
            self::ORDER_PLACED_IN_ERROR => __('Order placed in error'),
            self::OTHER => __('Other reason'),
        };
    }
}
