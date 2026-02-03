<?php

/*
 * author Arya Permana - Kirin
 * created on 04-07-2025-11h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;

class DispatchOrderFromDeliveryNote extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Order $order, DeliveryNote $deliveryNote): Order
    {
        return DispatchOrder::make()->action($order, $deliveryNote);
    }


    /**
     * @throws \Throwable
     */
    public function action(Order $order, DeliveryNote $deliveryNote): Order
    {
        return $this->handle($order, $deliveryNote);
    }


}
