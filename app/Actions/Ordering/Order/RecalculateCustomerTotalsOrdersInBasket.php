<?php

/*
 * author Louis Perez
 * created on 11-06-2026-13h-46m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Ordering\Order;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithRecalculateOrdersInBasket;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class RecalculateCustomerTotalsOrdersInBasket implements ShouldBeUnique
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithRecalculateOrdersInBasket;

    public function getJobUniqueId(int $customerId): string
    {
        return $customerId;
    }

    public function handle(int $customerId): void
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return;
        }

        $this->recalculateOrdersInBasket($customer->orders()->where('state', OrderStateEnum::CREATING)->get());
    }

}
