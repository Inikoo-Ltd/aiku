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
use App\Models\Dropshipping\CustomerClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerClientHydrateBasket implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(CustomerClient $customerClient): string
    {
        return $customerClient->id;
    }

    public function handle(CustomerClient $customerClient): void
    {
        $stats = [
            'amount_in_basket' => DB::table('orders')
                ->where('customer_client_id', $customerClient->id)
                ->where('state', OrderStateEnum::CREATING->value)
                ->sum('total_amount'),
            'current_order_in_basket_id' => DB::table('orders')
                ->where('customer_client_id', $customerClient->id)
                ->where('state', OrderStateEnum::CREATING->value)
                ->whereDate('date', now()->toDateString())
                ->value('id'),
        ];

        $customerClient->update($stats);
    }

}
