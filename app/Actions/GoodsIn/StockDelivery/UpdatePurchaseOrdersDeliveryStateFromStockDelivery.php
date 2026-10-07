<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Query\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePurchaseOrdersDeliveryStateFromStockDelivery
{
    use AsAction;
    use HasPurchaseOrderHydrators;

    private const SETTLED_DELIVERY_STATES = [
        PurchaseOrderDeliveryStateEnum::RECEIVED,
        PurchaseOrderDeliveryStateEnum::CHECKED,
        PurchaseOrderDeliveryStateEnum::PLACED,
    ];

    public function handle(StockDelivery $stockDelivery): void
    {
        $deliveryState = PurchaseOrderDeliveryStateEnum::tryFrom($stockDelivery->state->value);

        if ($deliveryState === null) {
            return;
        }

        foreach ($stockDelivery->purchaseOrders as $purchaseOrder) {
            $changed = false;

            if ($purchaseOrder->delivery_state !== $deliveryState) {
                $purchaseOrder->update(['delivery_state' => $deliveryState]);
                $changed = true;
            }

            if ($this->syncSettlement($purchaseOrder, $stockDelivery, $deliveryState)) {
                $changed = true;
            }

            if ($changed) {
                $this->purchaseOrderHydrate($purchaseOrder);
            }
        }
    }

    private function syncSettlement(PurchaseOrder $purchaseOrder, StockDelivery $stockDelivery, PurchaseOrderDeliveryStateEnum $deliveryState): bool
    {
        $shouldSettle = in_array($deliveryState, self::SETTLED_DELIVERY_STATES, true);

        $updatedTransactions = $purchaseOrder->purchaseOrderTransactions()
            ->whereExists(
                fn (Builder $items) => StoreStockDeliveryFromPurchaseOrder::deliveryItemsOfTransaction($items)
                    ->where('stock_delivery_items.stock_delivery_id', $stockDelivery->id)
                    ->whereNotIn('stock_delivery_items.state', [StockDeliveryItemStateEnum::CANCELLED->value, StockDeliveryItemStateEnum::NOT_RECEIVED->value])
            )
            ->where('state', $shouldSettle ? PurchaseOrderTransactionStateEnum::CONFIRMED : PurchaseOrderTransactionStateEnum::SETTLED)
            ->update(['state' => $shouldSettle ? PurchaseOrderTransactionStateEnum::SETTLED : PurchaseOrderTransactionStateEnum::CONFIRMED]);

        $isAwaitingDelivery = $purchaseOrder->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED)
            ->exists();

        if (!$isAwaitingDelivery && $purchaseOrder->state === PurchaseOrderStateEnum::CONFIRMED) {
            $purchaseOrder->update([
                'state'      => PurchaseOrderStateEnum::SETTLED,
                'settled_at' => now(),
            ]);

            return true;
        }

        if ($isAwaitingDelivery && $purchaseOrder->state === PurchaseOrderStateEnum::SETTLED) {
            $purchaseOrder->update([
                'state'      => PurchaseOrderStateEnum::CONFIRMED,
                'settled_at' => null,
            ]);

            return true;
        }

        return $updatedTransactions > 0;
    }

    public function action(StockDelivery $stockDelivery): void
    {
        $this->handle($stockDelivery);
    }
}
