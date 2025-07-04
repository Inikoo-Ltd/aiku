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

class InvoiceOrderFromDeliveryNoteFinalisation extends OrgAction
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order): Order
    {

        return FinaliseOrder::make()->action($order, true);


    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Order $order): Order
    {
        return $this->handle($order);
    }


}
