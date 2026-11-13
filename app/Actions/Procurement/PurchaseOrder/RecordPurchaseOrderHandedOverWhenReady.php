<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-05 09:41
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateState;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Clean Hand Over means the order is ready to be put on a shipment: once every required Ready to Roll check of the
 * agent (balance, QC, packing) is done, the hand over is recorded, dated by the last of them. When one of them is undone
 * the hand over recorded this way is cleared again, until the goods are dispatched. A hand over marked by a person, an
 * order waiting for its sample, or an agent sending on the next container (where the containers complete it) is left
 * alone.
 */
class RecordPurchaseOrderHandedOverWhenReady
{
    use AsObject;

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $purchaseOrder = $this->recordWhenReady($purchaseOrder);
        PurchaseOrderHydrateState::run($purchaseOrder);

        return $purchaseOrder;
    }

    private function recordWhenReady(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $purchaseOrder->refresh();

        if (!$purchaseOrder->isAgentOrder()
            || $purchaseOrder->choMode() === 'next_container'
            || in_array($purchaseOrder->state, [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::CANCELLED, PurchaseOrderStateEnum::NOT_RECEIVED])) {
            return $purchaseOrder;
        }

        $checks  = collect(GetPurchaseOrderStages::make()->readyChecks($purchaseOrder) ?? [])->filter(fn (array $check) => $check['required']);
        $isReady = $checks->isNotEmpty() && !$checks->contains(fn (array $check) => !$check['done']);

        if ($purchaseOrder->handed_over_at !== null) {
            return !$isReady && self::wasRecordedAutomatically($purchaseOrder) && !$this->isDispatched($purchaseOrder)
                ? $this->clearHandOver($purchaseOrder)
                : $purchaseOrder;
        }

        if (!$isReady || $purchaseOrder->isWaitingForSample()) {
            return $purchaseOrder;
        }

        return DB::transaction(function () use ($purchaseOrder, $checks) {
            $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, ['handed_over_at' => $checks->max('date')]);

            $marks                      = $purchaseOrder->stage_marks ?? [];
            $marks['handed_over_at']    = ['automatic' => true, 'at' => now()->toIso8601String()];
            $purchaseOrder->stage_marks = $marks;
            $purchaseOrder->saveQuietly();

            return $purchaseOrder;
        });
    }

    private function clearHandOver(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, ['handed_over_at' => null]);

            $marks = $purchaseOrder->stage_marks ?? [];
            unset($marks['handed_over_at']);
            $purchaseOrder->stage_marks = $marks;
            $purchaseOrder->saveQuietly();

            return $purchaseOrder;
        });
    }

    private function isDispatched(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->stockDeliveries()->whereIn('stock_deliveries.state', GetPurchaseOrderStages::SHIPPED_STATES)->exists()
            || in_array(StockDeliveryStateEnum::tryFrom((string) $purchaseOrder->delivery_state?->value), GetPurchaseOrderStages::SHIPPED_STATES);
    }

    /**
     * @param iterable<int|null> $purchaseOrderIds
     */
    public function forPurchaseOrderIds(iterable $purchaseOrderIds): void
    {
        $ids = collect($purchaseOrderIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        PurchaseOrder::whereIn('id', $ids)->get()->each(fn (PurchaseOrder $purchaseOrder) => $this->handle($purchaseOrder));
    }

    public static function wasRecordedAutomatically(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->handed_over_at !== null && (bool) data_get($purchaseOrder->stage_marks, 'handed_over_at.automatic');
    }
}
