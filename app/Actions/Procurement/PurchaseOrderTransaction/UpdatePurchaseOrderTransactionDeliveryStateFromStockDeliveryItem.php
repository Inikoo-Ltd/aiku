<?php

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem
{
    use AsAction;

    private const STATE_MAP = [
        StockDeliveryItemStateEnum::IN_PROCESS->value    => PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS,
        StockDeliveryItemStateEnum::CONFIRMED->value     => PurchaseOrderTransactionDeliveryStateEnum::CONFIRMED,
        StockDeliveryItemStateEnum::READY_TO_SHIP->value => PurchaseOrderTransactionDeliveryStateEnum::READY_TO_SHIP,
        StockDeliveryItemStateEnum::DISPATCHED->value    => PurchaseOrderTransactionDeliveryStateEnum::DISPATCHED,
        StockDeliveryItemStateEnum::RECEIVED->value      => PurchaseOrderTransactionDeliveryStateEnum::RECEIVED,
        StockDeliveryItemStateEnum::CHECKED->value       => PurchaseOrderTransactionDeliveryStateEnum::CHECKED,
        StockDeliveryItemStateEnum::PLACED->value        => PurchaseOrderTransactionDeliveryStateEnum::SETTLED,
        StockDeliveryItemStateEnum::CANCELLED->value     => PurchaseOrderTransactionDeliveryStateEnum::CANCELLED,
        StockDeliveryItemStateEnum::NOT_RECEIVED->value  => PurchaseOrderTransactionDeliveryStateEnum::NOT_RECEIVED,
    ];

    public function handle(StockDeliveryItem $stockDeliveryItem): void
    {
        $deliveryState = self::STATE_MAP[$stockDeliveryItem->state->value] ?? null;

        if ($deliveryState === null) {
            return;
        }

        foreach ($stockDeliveryItem->stockDelivery->purchaseOrders as $purchaseOrder) {
            $updated = $purchaseOrder->purchaseOrderTransactions()
                ->where('org_stock_id', $stockDeliveryItem->org_stock_id)
                ->where('delivery_state', '!=', $deliveryState)
                ->update(['delivery_state' => $deliveryState]);

            if ($updated > 0) {
                PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);
            }
        }
    }
}
