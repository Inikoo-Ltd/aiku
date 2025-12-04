<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateBasket implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int $customerId): string
    {
        return (string) $customerId;
    }

    public function handle(int $customerId): void
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        if ($customer->is_dropshipping || $customer->is_fulfilment) {
            return;
        }

        /** @var Order $order */
        $order = $customer->orders()->where('state', OrderStateEnum::CREATING->value)->first();

        $stats = [
            'amount_in_basket'           => $order ? $order->total_amount : 0,
            'current_order_in_basket_id' => $order?->id
        ];

        $customer->update($stats);
    }

}
