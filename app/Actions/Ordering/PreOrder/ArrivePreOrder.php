<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Everything in the pre-order is in the warehouse. A trade pallet delivery waits for staff to
 * type the real pallet quote, since it goes with the balance request; anything else asks for its
 * balance, or goes straight to the warehouse when there is nothing left to pay.
 */
class ArrivePreOrder
{
    use AsObject;

    public function handle(PreOrder $preOrder): PreOrder
    {
        $preOrder->update(['goods_arrived_at' => now()]);

        if ($preOrder->is_trade && $preOrder->has_pallet_delivery && $preOrder->pallet_quote_amount === null) {
            $preOrder->update(['state' => PreOrderStateEnum::AWAITING_PALLET_QUOTE]);
            HydratePreOrderReservedStock::run(HydratePreOrderReservedStock::make()->orgStockIds($preOrder));

            return $preOrder;
        }

        return RequestPreOrderBalance::run($preOrder);
    }
}
