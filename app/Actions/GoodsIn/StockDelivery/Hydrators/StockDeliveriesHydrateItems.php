<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 00:53:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\Hydrators;

use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class StockDeliveriesHydrateItems implements ShouldBeUnique
{
    use AsAction;


    public function handle(StockDelivery $stockDelivery): void
    {
        $stats = [
            'number_of_items' => $stockDelivery->items()->count(),
            'cost_items'      => $this->getTotalCostItem($stockDelivery),
            'gross_weight'    => $this->getGrossWeight($stockDelivery),
            'net_weight'      => $this->getNetWeight($stockDelivery)
        ];

        $checkedItemsCount = $stockDelivery->items()->where('state', StockDeliveryItemStateEnum::CHECKED)->count();
        $items             = $stockDelivery->items()->count();

        if (($checkedItemsCount === $items) && ($items > 0)) {
            $stats['state']                                 = StockDeliveryStateEnum::CHECKED;
            $stats['checked_at']                            = now();
            $stats[$stockDelivery->state->value . '_at']    = null;
        }

        $stockDelivery->update($stats);
    }

    public function getGrossWeight(StockDelivery $stockDelivery): float
    {
        $grossWeight = 0;

        foreach ($stockDelivery->items as $item) {
            foreach ($item->supplierProduct['tradeUnits'] as $tradeUnit) {
                $grossWeight += $item->supplierProduct['grossWeight'] * $tradeUnit->pivot->package_quantity;
            }
        }

        return $grossWeight;
    }

    public function getNetWeight(StockDelivery $stockDelivery): float
    {
        $netWeight = 0;

        foreach ($stockDelivery->items as $item) {
            foreach ($item->supplierProduct['tradeUnits'] as $tradeUnit) {
                $netWeight += $item->supplierProduct['netWeight'] * $tradeUnit->pivot->package_quantity;
            }
        }

        return $netWeight;
    }

    public function getTotalCostItem(StockDelivery $stockDelivery): float
    {
        $costItems = 0;

        foreach ($stockDelivery->items as $item) {
            $costItems += $item->unit_price * $item->supplierProduct['cost'];
        }

        return $costItems;
    }
}
