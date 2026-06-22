<?php

/*
 * author Louis Perez
 * created on 11-06-2026-13h-46m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Ordering\Order;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;

class RecalculateCustomerTotalsOrdersInBasket implements ShouldBeUnique
{
    use WithActionUpdate;
    use WithFixedAddressActions;

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
        /** @var Order $order */
        foreach ($customer->orders()->where('state', OrderStateEnum::CREATING)->get() as $order) {
            if ($order->updated_by_customer_at && $order->updated_by_customer_at->isAfter(Carbon::now()->subHours(3))) {
                CalculateOrderDiscounts::dispatch($order);
            } else {
                $randomDelay = rand(300, 7200);
                CalculateOrderDiscounts::dispatch($order)->delay($randomDelay)->onQueue('hydrators-slave-low-priority');
            }
        }
    }

}
