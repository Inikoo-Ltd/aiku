<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-14h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Iris\Basket;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\IrisAction;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreEcomBasket extends IrisAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer): Order
    {
        $order = StoreOrder::make()->action($customer, []);
        $customer->refresh();
        CustomerHydrateBasket::dispatch($customer);

        return $order;
    }

    public function action(Customer $customer): Order
    {
        return $this->handle($customer);
    }


}
