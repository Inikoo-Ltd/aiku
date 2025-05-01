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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateBasket implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Customer $customer): string
    {
        return $customer->id;
    }

    public function handle(Customer $customer): void
    {

        if ($customer->is_dropshipping || $customer->is_fulfilment) {
            return;
        }

        $order = $customer->orders()->where('state', OrderStateEnum::CREATING->value)->first();

        $stats = [
            'amount_in_basket'           => $order ? $order->total_amount : 0,
            'current_order_in_basket_id' => $order?->id
        ];

        $customer->update($stats);
    }

}
