<?php

/*
 * author Arya Permana - Kirin
 * created on 20-06-2025-15h-59m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;

trait WithBasketStateWarning
{
    public function getWarnings(Order $order)
    {
        return match ($order->state) {
            OrderStateEnum::SUBMITTED => [
                'success' => false,
                'message' => 'Order has been submitted',
                'order'   => $order,
            ],
            OrderStateEnum::IN_WAREHOUSE => [
                'success' => false,
                'message' => 'Order is in the warehouse, waiting to be picked',
                'order'   => $order,
            ],
            OrderStateEnum::HANDLING => [
                'success' => false,
                'message' => 'Order is currently being picked',
                'order'   => $order,
            ],
            OrderStateEnum::HANDLING_BLOCKED => [
                'success' => false,
                'message' => 'Order picking is currently blocked',
                'order'   => $order,
            ],
            OrderStateEnum::PACKED => [
                'success' => false,
                'message' => 'Order is packed and ready for dispatch',
                'order'   => $order,
            ],
            OrderStateEnum::FINALISED => [
                'success' => false,
                'message' => 'Order has been invoiced and ready to be dispatched',
                'order'   => $order,
            ],
            OrderStateEnum::DISPATCHED => [
                'success' => false,
                'message' => 'Order has been dispatched',
                'order'   => $order,
            ],
            OrderStateEnum::CANCELLED => [
                'success' => false,
                'reason'  => 'Order has been cancelled',
                'order'   => $order,
            ],
            default => null
        };
    }
}
