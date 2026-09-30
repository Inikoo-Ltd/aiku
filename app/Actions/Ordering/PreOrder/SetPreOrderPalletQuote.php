<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Ordering\Order\UpdateOrderShippingEngineAsManual;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The real pallet cost replaces the estimate as the order's delivery charge. Trade customers are
 * sent it with the balance request; above the shop's tolerance they may cancel with their
 * deposit back.
 */
class SetPreOrderPalletQuote
{
    use AsObject;

    /**
     * @throws \Throwable
     */
    public function handle(PreOrder $preOrder, float $amount): PreOrder
    {
        return DB::transaction(function () use ($preOrder, $amount) {
            $preOrder->lockInState(PreOrderStateEnum::open());
            $preOrder->update(['pallet_quote_amount' => $amount]);

            UpdateOrderShippingEngineAsManual::run($preOrder->order, ['shipping_amount' => $amount]);

            if ($preOrder->state == PreOrderStateEnum::AWAITING_PALLET_QUOTE) {
                return RequestPreOrderBalance::run($preOrder);
            }

            return $preOrder;
        });
    }
}
