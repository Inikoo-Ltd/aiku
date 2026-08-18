<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UndoPackingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UndoSetAsPickedDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UnpackDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;

/**
 * A quantity can change after the warehouse has already worked the delivery note, either because
 * somebody edited the order or because the marketplace the order came from is the source of truth
 * and changed it for us. The note cannot simply be rewritten underneath stock that is already in a
 * tote, so the lines are flagged dirty and the note is walked back to the state that lets a human
 * pick it again.
 */
trait WithDeliveryNoteQuantitySync
{
    use HasDeliveryNoteHydrators;


    /**
     * @param  Collection  $orgStocks  the transaction's product org stocks, keyed by id
     */
    protected function syncDeliveryNote(DeliveryNote $deliveryNote, Transaction $transaction, Collection $orgStocks, ?User $user): void
    {
        $goBackToPicking = false;
        $quantityLowered = false;

        $deliveryNoteItems = $transaction
            ->deliveryNoteItems()
            ->where('delivery_note_id', $deliveryNote->id)
            ->lockForUpdate()
            ->get();

        foreach ($deliveryNoteItems as $deliveryNoteItem) {
            $orgStock = $orgStocks->get($deliveryNoteItem->org_stock_id);
            if (!$orgStock) {
                continue;
            }

            $quantity            = $orgStock->pivot->quantity * ($transaction->quantity_ordered + $transaction->quantity_bonus);
            $oldRequiredQuantity = (float)$deliveryNoteItem->quantity_required;

            if (abs($quantity - $oldRequiredQuantity) < 0.000001) {
                continue;
            }

            $dataToBeUpdated = [
                'quantity_required' => $quantity,
                'is_dirty'          => true,
            ];

            if (!$deliveryNoteItem->original_quantity_required) {
                $dataToBeUpdated['original_quantity_required'] = $oldRequiredQuantity;
            }

            $deliveryNoteItem->update($dataToBeUpdated);

            if (abs($quantity - (float)$deliveryNoteItem->quantity_picked) > 0.000001) {
                $goBackToPicking = true;
            }
            if ($quantity < $oldRequiredQuantity) {
                $quantityLowered = true;
            }

            CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);
        }

        $this->walkDeliveryNoteBackToPicking($deliveryNote, $goBackToPicking, $quantityLowered, $user);
    }

    /**
     * Undoing the packing is what deletes the packings and frees the parcels; a raw state change
     * would leave that physical work recorded against quantities that no longer exist.
     */
    protected function walkDeliveryNoteBackToPicking(DeliveryNote $deliveryNote, bool $goBackToPicking, bool $quantityLowered, ?User $user): void
    {
        if (!$goBackToPicking && !$quantityLowered) {
            return;
        }

        if ($deliveryNote->state == DeliveryNoteStateEnum::PACKED) {
            $deliveryNote = UnpackDeliveryNote::make()->action($deliveryNote, $user);
        }

        if ($goBackToPicking) {
            if ($deliveryNote->state == DeliveryNoteStateEnum::PACKING) {
                $deliveryNote = UndoPackingDeliveryNote::make()->action($deliveryNote, $user);
            }
            if ($deliveryNote->state == DeliveryNoteStateEnum::PICKED) {
                UndoSetAsPickedDeliveryNote::make()->action($deliveryNote, $user);
            }

            /*
             * A blocked note is waiting on its own items, but the line that just changed still has
             * to be walked to. Auto finish waiting only looks at the waiting flags, so a note left
             * blocked would jump to picked with this line never picked and the order would ship
             * short. Picking recomputes the block when it finishes, so nothing is lost by it.
             */
            if ($deliveryNote->state == DeliveryNoteStateEnum::HANDLING_BLOCKED) {
                $oldState = $deliveryNote->state;
                $deliveryNote->update([
                    'state'               => DeliveryNoteStateEnum::HANDLING->value,
                    'handling_blocked_at' => null,
                ]);

                $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
                $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::HANDLING);
            }
        }
    }
}
