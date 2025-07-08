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
        $customer = $order->customer;

        if ($customer->balance >= $order->total_amount) {
            PayRetinaOrderWithBalance::run($order);
        } else {
            foreach ($customer->mitSavedCard->sortBy('priority') as $card) {
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
        $order = Order::find(1186846);


        $this->handle($order);


        return 1;
    }

}
