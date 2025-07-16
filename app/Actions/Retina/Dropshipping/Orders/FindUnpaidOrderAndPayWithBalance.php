<?php

/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-16h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class FindUnpaidOrderAndPayWithBalance extends RetinaAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData)
    {
        $amount = Arr::pull($modelData, 'amount');
        $orders = $customer->orders()->whereNotIn('state', [OrderStateEnum::CANCELLED, OrderStateEnum::CREATING])
                    ->whereRaw('payment_amount < total_amount')->get();

        $sortedOrders = $orders->sortBy(function ($order) {
            return $order->total_amount - $order->pay_amount;
        });

        $filteredOrders = collect();
        $remainingAmount = $amount;
        
        foreach ($sortedOrders as $order) {
            $unpaid = $order->total_amount - $order->pay_amount;

            if ($unpaid <= $remainingAmount) {
                $filteredOrders->push($order);
                $remainingAmount -= $unpaid;
            }
        }

        if ($filteredOrders->isNotEmpty()) {
            foreach ($filteredOrders as $order) {
                PayRetinaOrderWithBalance::run($order);
            }
        }
    }
}
