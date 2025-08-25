<?php

/*
 * author Arya Permana - Kirin
 * created on 04-07-2025-11h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Models\Ordering\Order;

class DispatchOrderFromDeliveryNote extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        return DispatchOrder::make()->action($order);
    }


    /**
     * @throws \Throwable
     */
    public function action(Order $order): Order
    {
        return $this->handle($order);
    }


}
