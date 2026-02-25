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

        // If customer turns on disable order auto processing on their profile settings, would not process it automatically | Done as per Maria request from Customer complaint. 
        // By default this is false, so everything would run as it is unless they turn this setting on.
        if(data_get($customer->settings, 'disable_order_auto_processing', false)) {
            return;    
        }

        if ($customer->balance >= $order->total_amount) {
            PayRetinaOrderWithBalance::run($order, false);
        } else {
            foreach ($customer->mitSavedCard->sortBy('priority') as $card) {
                if ($card->state == 'success') {
                    $result = PayOrderWithMitCard::run($order, $card);
                    if (Arr::get($result, 'status') == 'ok') {
                        break;
                    }
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
