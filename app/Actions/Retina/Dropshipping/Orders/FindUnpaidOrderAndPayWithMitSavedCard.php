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
use App\Models\Accounting\MitSavedCard;
use App\Models\CRM\Customer;

class FindUnpaidOrderAndPayWithMitSavedCard extends RetinaAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, MitSavedCard $mitSavedCard)
    {
        $orders = $customer->orders()->whereNotIn('state', [OrderStateEnum::CANCELLED, OrderStateEnum::CREATING])
                    ->whereRaw('payment_amount < total_amount')->get();

        if ($orders->isNotEmpty()) {
            foreach ($orders as $order) {
                PayRemainingOrderWithMitCard::run($order, $mitSavedCard);
            }
        }
    }
}
