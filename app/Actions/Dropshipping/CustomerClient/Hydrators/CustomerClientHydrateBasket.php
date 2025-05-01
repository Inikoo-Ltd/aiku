<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 May 2025 11:10:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerClient\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\CustomerClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
        $order = $customerClient->orders()->where('state', OrderStateEnum::CREATING->value)->orderBy('date', 'desc')->first();

        $stats = [
            'amount_in_basket'           => $order ? $order->total_amount : 0,
            'current_order_in_basket_id' => $order?->id
        ];

        $customerClient->update($stats);
    }

}
