<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\Concerns\AsObject;

class LogBasketEvent
{
    use AsObject;

    /**
     * Appends one line to the order's basket history: what changed, by how much, and what the whole
     * basket was worth once it had. Transactions carry no audit trail, so without this every
     * quantity change and removal vanished - the marketing timeline could show what landed in the
     * basket but never what was taken out or trimmed down.
     *
     * Lives on the order's data json rather than the transaction, so a removed product's history
     * survives its row. Only baskets are logged: once an order leaves CREATING its lines change
     * through amendment flows that have their own records.
     */
    public function handle(Order $order, string $event, Transaction $transaction, float $quantityDelta): void
    {
        if ($order->state !== OrderStateEnum::CREATING || $transaction->model_type !== 'Product' || $quantityDelta == 0.0) {
            return;
        }

        try {
            $log   = (array) data_get($order->data, 'basket_log', []);
            $log[] = [
                't'      => now()->toIso8601String(),
                'e'      => $event,
                'asset'  => $transaction->asset?->name ?? $transaction->asset?->code,
                'q'      => round($quantityDelta, 3),
                'basket' => (float) $order->net_amount,
            ];

            $order->update(['data' => array_merge((array) $order->data, ['basket_log' => $log])]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
