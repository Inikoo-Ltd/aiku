<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The customer ticks the pre-order terms at checkout, and picks whether the in-stock items
 * wait for the pre-order ones. What they saw is kept on the order as the record of acceptance.
 */
class AcceptBasketPreOrderTerms
{
    use AsObject;

    public function handle(Order $order, bool $holdTogether): Order
    {
        $preOrders = GetBasketPreOrders::run($order);

        $order->update([
            'data' => array_merge($order->data ?? [], [
                'pre_order' => [
                    'hold_together'      => $holdTogether,
                    'accepted_at'        => now()->toIso8601String(),
                    'accepted_signature' => $preOrders['signature'],
                    'accepted_terms'     => $preOrders['terms'],
                ],
            ]),
        ]);

        return $order;
    }
}
