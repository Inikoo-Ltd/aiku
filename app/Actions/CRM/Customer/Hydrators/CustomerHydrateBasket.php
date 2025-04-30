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
use Illuminate\Support\Facades\DB;
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
        $stats = [
            'amount_in_basket' => DB::table('orders')
                ->where('customer_id', $customer->id)
                ->where('state', OrderStateEnum::CREATING->value)
                ->sum('total_amount'),
            'current_order_in_basket_id' => DB::table('orders')
                ->where('customer_id', $customer->id)
                ->where('state', OrderStateEnum::CREATING->value)
                ->whereDate('date', now()->toDateString())
                ->value('id'),
        ];

        $customer->update($stats);
    }

}
