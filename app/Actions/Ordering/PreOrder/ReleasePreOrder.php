<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A paid pre-order with its goods here goes to the warehouse. The delivery note takes over
 * keeping the goods, so the pre-order stops reserving them only after it exists.
 */
class ReleasePreOrder
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function handle(PreOrder $preOrder): PreOrder
    {
        $preOrder->refresh();
        if (!in_array($preOrder->state, PreOrderStateEnum::open())) {
            return $preOrder;
        }

        $wasHoldingStock = in_array($preOrder->state, PreOrderStateEnum::holdingStock());

        $preOrder->update([
            'state'           => PreOrderStateEnum::RELEASED,
            'released_at'     => now(),
            'balance_paid_at' => $preOrder->balance_requested_at ? now() : null,
        ]);

        SendOrderToWarehouse::make()->action($preOrder->order->refresh(), []);

        if ($wasHoldingStock) {
            HydratePreOrderReservedStock::run(HydratePreOrderReservedStock::make()->orgStockIds($preOrder));
        }

        return $preOrder;
    }
}
