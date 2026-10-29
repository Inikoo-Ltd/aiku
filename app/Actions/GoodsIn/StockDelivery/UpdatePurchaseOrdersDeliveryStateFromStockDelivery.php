<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Procurement\PurchaseOrderTransaction\CancelPurchaseOrderTransaction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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
            $changed = $this->syncSettlement($purchaseOrder, $stockDelivery, $deliveryState);

            $keepsSettledDeliveryState = $purchaseOrder->state === PurchaseOrderStateEnum::SETTLED
                && !in_array($deliveryState, self::SETTLED_DELIVERY_STATES, true);

            if (!$keepsSettledDeliveryState && $purchaseOrder->delivery_state !== $deliveryState) {
                $purchaseOrder->update(['delivery_state' => $deliveryState]);
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
            ->when($shouldSettle && !$stockDelivery->delivery_note_id, fn ($transactions) => $this->whereAllArrived($transactions))
            ->update(['state' => $shouldSettle ? PurchaseOrderTransactionStateEnum::SETTLED : PurchaseOrderTransactionStateEnum::CONFIRMED]);

        if ($shouldSettle && $stockDelivery->delivery_note_id) {
            $this->cancelWhatThePartnerDidNotSend($purchaseOrder);
        }

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

        if (!$shouldSettle && $updatedTransactions > 0 && $isAwaitingDelivery && $purchaseOrder->state === PurchaseOrderStateEnum::SETTLED) {
            $purchaseOrder->update([
                'state'      => PurchaseOrderStateEnum::CONFIRMED,
                'settled_at' => null,
            ]);

            return true;
        }

        return $updatedTransactions > 0;
    }

    /**
     * A partner's dispatched delivery is all it sends for the order: the lines it left out are cancelled.
     */
    private function cancelWhatThePartnerDidNotSend(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED)
            ->whereNotExists(fn (Builder $items) => StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction($items))
            ->get()
            ->each(fn (PurchaseOrderTransaction $purchaseOrderTransaction) => CancelPurchaseOrderTransaction::make()->action($purchaseOrderTransaction));
    }

    /**
     * A line an agent split across containers is settled once all of it is in containers and none of them is still
     * on its way.
     */
    private function whereAllArrived(EloquentBuilder $transactions): EloquentBuilder
    {
        $quantityOnDelivery = StoreStockDeliveryFromPurchaseOrder::quantityOnDelivery();

        return $transactions
            ->whereRaw('purchase_order_transactions.quantity_ordered - ('.$quantityOnDelivery->toSql().') <= '.StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE, $quantityOnDelivery->getBindings())
            ->whereNotExists(
                fn (Builder $items) => StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction($items)
                    ->join('stock_deliveries as other_deliveries', 'other_deliveries.id', 'stock_delivery_items.stock_delivery_id')
                    ->whereIn('other_deliveries.state', [
                        StockDeliveryStateEnum::IN_PROCESS->value,
                        StockDeliveryStateEnum::CONFIRMED->value,
                        StockDeliveryStateEnum::READY_TO_SHIP->value,
                        StockDeliveryStateEnum::DISPATCHED->value,
                    ])
            );
    }

    public function action(StockDelivery $stockDelivery): void
    {
        $this->handle($stockDelivery);
    }
}
