<?php

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateDeliveryState;
use App\Models\GoodsIn\StockDeliveryItem;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Queued, one job per purchase order: dispatching or receiving a delivery runs this for every item and then
 * UpdatePurchaseOrdersDeliveryStateFromStockDelivery once for the whole delivery.
 */
class UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem
{
    use AsAction;

    public function handle(StockDeliveryItem $stockDeliveryItem): void
    {
        foreach ($stockDeliveryItem->stockDelivery->purchaseOrders as $purchaseOrder) {
            PurchaseOrderHydrateDeliveryState::dispatch($purchaseOrder);
        }
    }
}
