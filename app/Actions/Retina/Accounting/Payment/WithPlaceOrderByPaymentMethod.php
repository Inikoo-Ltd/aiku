<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Sept 2025 22:51:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\Payment;

use App\Actions\Ordering\Order\SubmitOrder;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;

trait WithPlaceOrderByPaymentMethod
{
    /**
     * Common implementation to place an order for a given customer's current basket
     * and set the payment method designation, wrapped in a DB transaction.
     *
     * @throws \Throwable
     */
    protected function placeOrderByPaymentMethod(Customer $customer, OrderToBePaidByEnum $method): array
    {
        $order = Order::find($customer->current_order_in_basket_id);
        if (!$order) {
            return [
                'success' => false,
                'reason'  => 'Order not found',
                'order'   => null,
            ];
        }

        $order = DB::transaction(function () use ($order, $method) {
            $order->updateQuietly([
                'to_be_paid_by' => $method,
            ]);

            return SubmitOrder::run($order);
        });

        return [
            'success' => true,
            'reason'  => 'Order submitted successfully',
            'order'   => $order,
        ];
    }
}
