<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Costing lives on the stock delivery and is spread over its items. A purchase order only learns whether its
 * deliveries are costed: its amounts stay what was ordered, as deposits and agent money are reckoned from them.
 */
class UpdatePurchaseOrdersCostedFromStockDelivery
{
    use AsAction;

    public function handle(StockDelivery $stockDelivery): void
    {
        foreach ($stockDelivery->purchaseOrders as $purchaseOrder) {
            $purchaseOrder->update(['is_costed' => $this->isCosted($purchaseOrder)]);
        }
    }

    private function isCosted(PurchaseOrder $purchaseOrder): bool
    {
        $stockDeliveries = $purchaseOrder->stockDeliveries()
            ->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)
            ->get(['stock_deliveries.id', 'stock_deliveries.state', 'stock_deliveries.is_costed']);

        return $stockDeliveries->contains(fn (StockDelivery $stockDelivery) => $stockDelivery->state === StockDeliveryStateEnum::PLACED)
            && $stockDeliveries->every(fn (StockDelivery $stockDelivery) => $stockDelivery->is_costed);
    }
}
