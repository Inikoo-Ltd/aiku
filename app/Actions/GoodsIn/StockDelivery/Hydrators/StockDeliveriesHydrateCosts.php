<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDelivery\Hydrators;

use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersCostFromStockDelivery;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class StockDeliveriesHydrateCosts implements ShouldBeUnique
{
    use AsAction;

    public function handle(StockDelivery $stockDelivery): void
    {
        $totals = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->selectRaw('coalesce(sum(cost_items), 0) as cost_items')
            ->selectRaw('coalesce(sum(cost_extra), 0) as cost_extra')
            ->selectRaw('coalesce(sum(cost_shipping), 0) as cost_shipping')
            ->selectRaw('coalesce(sum(cost_duties), 0) as cost_duties')
            ->selectRaw('coalesce(sum(cost_tax), 0) as cost_tax')
            ->selectRaw('coalesce(sum(cost_total), 0) as cost_total')
            ->first();

        $stockDelivery->update([
            'cost_items'    => $totals->cost_items,
            'cost_extra'    => $totals->cost_extra,
            'cost_shipping' => $totals->cost_shipping,
            'cost_duties'   => $totals->cost_duties,
            'cost_tax'      => $totals->cost_tax,
            'cost_total'    => $totals->cost_total,
        ]);

        UpdatePurchaseOrdersCostFromStockDelivery::run($stockDelivery);
    }
}
