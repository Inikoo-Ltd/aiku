<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Feb 2026 12:14:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;

trait InteractsWithOrderInBasket
{
    protected function getOrderInBasket(Customer $customer): ?Order
    {
        $order = $customer->orderInBasket;

        if ($order && $order->state != OrderStateEnum::CREATING) {
            $order = Order::where('customer_id', $customer->id)
                ->where('state', OrderStateEnum::CREATING)
                ->first();

            if ($order) {
                $customer->update([
                    'current_order_in_basket_id' => $order->id,
                ]);
                CustomerHydrateBasket::run($customer->id);
            }
        }

        return $order;
    }
}
