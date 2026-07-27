<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
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

            if ($this->syncSettlement($purchaseOrder, $deliveryState)) {
                $changed = true;
            }

            if ($changed) {
                $this->purchaseOrderHydrate($purchaseOrder);
            }
        }
    }

    private function syncSettlement(PurchaseOrder $purchaseOrder, PurchaseOrderDeliveryStateEnum $deliveryState): bool
    {
        $shouldSettle = in_array($deliveryState, self::SETTLED_DELIVERY_STATES, true);

        if ($shouldSettle && $purchaseOrder->state !== PurchaseOrderStateEnum::SETTLED) {
            $purchaseOrder->update([
                'state'      => PurchaseOrderStateEnum::SETTLED,
                'settled_at' => now(),
            ]);

            $purchaseOrder->purchaseOrderTransactions()
                ->whereNotIn('state', [PurchaseOrderTransactionStateEnum::CANCELLED, PurchaseOrderTransactionStateEnum::NOT_RECEIVED])
                ->update(['state' => PurchaseOrderTransactionStateEnum::SETTLED]);

            return true;
        }

        if (!$shouldSettle && $purchaseOrder->state === PurchaseOrderStateEnum::SETTLED) {
            $purchaseOrder->update([
                'state'      => PurchaseOrderStateEnum::CONFIRMED,
                'settled_at' => null,
            ]);

            $purchaseOrder->purchaseOrderTransactions()
                ->whereNotIn('state', [PurchaseOrderTransactionStateEnum::CANCELLED, PurchaseOrderTransactionStateEnum::NOT_RECEIVED])
                ->update(['state' => PurchaseOrderTransactionStateEnum::CONFIRMED]);

            return true;
        }

        return false;
    }

    public function action(StockDelivery $stockDelivery): void
    {
        $this->handle($stockDelivery);
    }
}
