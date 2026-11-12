<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 12:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\Hydrators;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A purchase order can ship in several stock deliveries, a line in part, so a line is only as far as the units of it
 * that are furthest behind, and the order as far as its line furthest behind: a line with units in no delivery yet is
 * in process, and an order with 18 of 29 lines dispatched is not dispatched. An item counts as far as the further of its
 * own state and its delivery's: costing places a delivery without placing its items, and Aurora moves items ahead of
 * their delivery.
 */
class PurchaseOrderHydrateDeliveryState implements ShouldBeUnique
{
    use AsAction;
    use HasPurchaseOrderHydrators;

    private const PROGRESSION = [
        PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS,
        PurchaseOrderTransactionDeliveryStateEnum::CONFIRMED,
        PurchaseOrderTransactionDeliveryStateEnum::READY_TO_SHIP,
        PurchaseOrderTransactionDeliveryStateEnum::DISPATCHED,
        PurchaseOrderTransactionDeliveryStateEnum::RECEIVED,
        PurchaseOrderTransactionDeliveryStateEnum::CHECKED,
        PurchaseOrderTransactionDeliveryStateEnum::SETTLED,
    ];

    private const STOCK_DELIVERY_STATE_RANKS = [
        'in_process'    => 0,
        'confirmed'     => 1,
        'ready_to_ship' => 2,
        'dispatched'    => 3,
        'received'      => 4,
        'checked'       => 5,
        'booking_in'    => 5,
        'booked_in'     => 5,
        'placed'        => 6,
    ];

    private const SETTLED_DELIVERY_STATES = [
        PurchaseOrderDeliveryStateEnum::RECEIVED,
        PurchaseOrderDeliveryStateEnum::CHECKED,
        PurchaseOrderDeliveryStateEnum::PLACED,
    ];

    public function getJobUniqueId(PurchaseOrder $purchaseOrder): string
    {
        return (string) $purchaseOrder->id;
    }

    /**
     * Returns whether the order's delivery state changed.
     */
    public function handle(PurchaseOrder $purchaseOrder): bool
    {
        $changed = $this->hydrateDeliveryState($purchaseOrder);
        PurchaseOrderHydrateStage::run($purchaseOrder);

        return $changed;
    }

    private function hydrateDeliveryState(PurchaseOrder $purchaseOrder): bool
    {
        $quantityOnDelivery = StoreStockDeliveryFromPurchaseOrder::quantityOnDelivery();
        $itemStates         = StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction(DB::query())
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->selectRaw("string_agg(distinct stock_delivery_items.state || '/' || stock_deliveries.state, ',')");

        $lines = $purchaseOrder->purchaseOrderTransactions()
            ->whereNotIn('state', [PurchaseOrderTransactionStateEnum::CANCELLED, PurchaseOrderTransactionStateEnum::NOT_RECEIVED])
            ->select('purchase_order_transactions.id', 'purchase_order_transactions.quantity_ordered', 'purchase_order_transactions.delivery_state')
            ->selectRaw('('.$quantityOnDelivery->toSql().') as quantity_on_delivery', $quantityOnDelivery->getBindings())
            ->selectRaw('('.$itemStates->toSql().') as item_states', $itemStates->getBindings())
            ->get();

        $slowestRank  = null;
        $linesChanged = false;
        foreach ($lines as $line) {
            $lineState = $this->lineState($line);
            if ($lineState === null) {
                continue;
            }

            if ($line->delivery_state !== $lineState) {
                PurchaseOrderTransaction::whereKey($line->id)->update(['delivery_state' => $lineState]);
                $linesChanged = true;
            }

            $rank        = array_search($lineState, self::PROGRESSION, true);
            $slowestRank = $slowestRank === null ? $rank : min($slowestRank, $rank);
        }

        if ($linesChanged) {
            PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);
        }

        if ($slowestRank === null) {
            return false;
        }

        $orderState = self::PROGRESSION[$slowestRank] === PurchaseOrderTransactionDeliveryStateEnum::SETTLED
            ? PurchaseOrderDeliveryStateEnum::PLACED
            : PurchaseOrderDeliveryStateEnum::from(self::PROGRESSION[$slowestRank]->value);

        $keepsSettledDeliveryState = $purchaseOrder->state === PurchaseOrderStateEnum::SETTLED
            && !in_array($orderState, self::SETTLED_DELIVERY_STATES, true);

        if ($keepsSettledDeliveryState || $purchaseOrder->delivery_state === $orderState) {
            return false;
        }

        $purchaseOrder->update(['delivery_state' => $orderState]);
        $this->purchaseOrderHydrate($purchaseOrder);

        return true;
    }

    private function lineState(PurchaseOrderTransaction $line): ?PurchaseOrderTransactionDeliveryStateEnum
    {
        if ((float) $line->quantity_ordered - (float) $line->getAttribute('quantity_on_delivery') > StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE) {
            return PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS;
        }

        $itemStates = array_filter(explode(',', (string) $line->getAttribute('item_states')));
        if ($itemStates === []) {
            return null;
        }

        $ranks = array_map(function (string $itemAndDeliveryState) {
            [$itemState, $deliveryState] = explode('/', $itemAndDeliveryState);

            return max(self::STOCK_DELIVERY_STATE_RANKS[$itemState] ?? 0, self::STOCK_DELIVERY_STATE_RANKS[$deliveryState] ?? 0);
        }, $itemStates);

        return self::PROGRESSION[min($ranks)];
    }
}
