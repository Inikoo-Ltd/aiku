<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Asset;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Asset;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A product's tax treatment changed, so every basket holding it is now quoting the wrong VAT.
 *
 * Baskets only. An order that is already submitted was invoiced at the rate in force when the
 * customer agreed to it, and re-rating it is an accounting decision, not a recalculation - the
 * same reasoning that put the `onlyIfInBasket` guard on CalculateOrderTotalAmounts after a bulk
 * job repriced submitted orders. The state is filtered here and rechecked when each job runs,
 * because a customer can submit while this queue is draining.
 */
class RecalculateBasketsAfterTaxCategoryChange implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'price_change';

    public function getJobUniqueId(Asset $asset): string
    {
        return $asset->id;
    }

    public function handle(Asset $asset): void
    {
        Order::where('state', OrderStateEnum::CREATING)
            ->whereIn('id', Transaction::where('asset_id', $asset->id)->select('order_id'))
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    /** Tax does not move the discounts or the shipping, so leave both alone. */
                    CalculateOrderTotalAmounts::dispatch($order, false, false, false, false, true);
                }
            });
    }
}
