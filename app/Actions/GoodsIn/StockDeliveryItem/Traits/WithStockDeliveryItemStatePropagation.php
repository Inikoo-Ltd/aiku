<?php

namespace App\Actions\GoodsIn\StockDeliveryItem\Traits;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateFromItems;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem;
use App\Models\GoodsIn\StockDeliveryItem;

trait WithStockDeliveryItemStatePropagation
{
    protected function propagateStockDeliveryItemStateChange(StockDeliveryItem $stockDeliveryItem): void
    {
        StockDeliveriesHydrateItems::dispatch($stockDeliveryItem->stockDelivery);
        UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem::run($stockDeliveryItem);
        UpdateStockDeliveryStateFromItems::run($stockDeliveryItem->stockDelivery);
    }
}
