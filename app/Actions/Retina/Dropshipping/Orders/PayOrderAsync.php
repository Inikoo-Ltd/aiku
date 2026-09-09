<?php

/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-17h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

class PayOrderAsync extends RetinaAction
{
    use WithBasketStateWarning;
    use WithRetinaOrderPlacedRedirection;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): void
    {
        if ($this->getWarnings($order)) {
            return;
        }

        SettleRetinaOrderWithBalance::run($order);
        $order->refresh();

        if (round($order->total_amount - $order->payment_amount, 2) <= 0) {
            return;
        }

        foreach ($order->customer->mitSavedCard->sortBy('priority') as $card) {
            if ($card->state == 'success') {
                $result = PayOrderWithMitCard::run($order, $card);
                if (Arr::get($result, 'status') == 'ok') {
                    break;
                }
            }
        }
    }

    public string $commandSignature = 'test_pay2';

    /**
     * @throws \Throwable
     */
    public function asCommand(): int
    {
        $order = Order::find(1281261);

        $this->handle($order);


        return 1;
    }

}
