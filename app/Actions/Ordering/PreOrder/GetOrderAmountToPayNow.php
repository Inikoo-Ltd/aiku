<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What a checkout charges, for every way of paying. A basket with trade made-to-order items is
 * charged the deposit on them, not their full price (HELP-3432); any other basket its total,
 * exactly as before pre-orders existed. A submitted order, such as a pre-order whose balance is
 * requested, is charged what it still owes.
 */
class GetOrderAmountToPayNow
{
    use AsObject;

    public function handle(Order $order): float
    {
        if ($order->state != OrderStateEnum::CREATING) {
            return round(max(0, (float) $order->total_amount - (float) $order->payment_amount), 2);
        }

        if (!$order->shop->hasPreOrders()) {
            return (float) $order->total_amount;
        }

        $basketPreOrders = GetBasketPreOrders::run($order);

        return $basketPreOrders['is_accepted'] ? $basketPreOrders['pay_now_amount'] : (float) $order->total_amount;
    }
}
