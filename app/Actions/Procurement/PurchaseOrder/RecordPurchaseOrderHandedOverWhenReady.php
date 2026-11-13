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
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Clean Hand Over means the order is ready to be put on a shipment: once every Ready to Roll check of the agent
 * (balance, packing, QC, compliance) is done, the hand over is recorded, dated by the last of them. An order whose
 * hand over is already recorded, waiting for its sample, or with an agent sending on the next container (where the
 * containers complete it) is left alone.
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

        if ($purchaseOrder->handed_over_at !== null
            || !$purchaseOrder->isAgentOrder()
            || $purchaseOrder->choMode() === 'next_container'
            || $purchaseOrder->isWaitingForSample()
            || in_array($purchaseOrder->state, [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::CANCELLED, PurchaseOrderStateEnum::NOT_RECEIVED])) {
            return $purchaseOrder;
        }

        $checks = collect(GetPurchaseOrderStages::make()->readyChecks($purchaseOrder) ?? [])->filter(fn (array $check) => $check['required']);
        if ($checks->isEmpty() || $checks->contains(fn (array $check) => !$check['done'])) {
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
