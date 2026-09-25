<?php

/*
 * Author: Arya Permana - Kirin
 * Created: Fri, 23 May 2025 11:05:01 Malaysia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNote\CalculateDeliveryNotePercentage;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToHandlingBlocked;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UndoSetAsPickedDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;

class CalculateDeliveryNoteItemTotalPicked extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;
    use WithScannedDeliveryNoteItemPicking;

    /**
     * Quantities are held to six decimals and a cut of a pack lands on a repeating decimal, so the
     * comparisons below carry a tolerance rather than asking two floats to be equal.
     */
    private const QUANTITY_TOLERANCE = 0.000001;

    public function handle(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        $pickings = $deliveryNoteItem->pickings()->get();


        $totalPicked = $pickings->whereIn('type', [
            PickingTypeEnum::PICK,
            PickingTypeEnum::MAGIC_PICK
        ])->sum('quantity');

        $totalWaiting   = $deliveryNoteItem->quantity_waiting_warehouse + $deliveryNoteItem->quantity_waiting_crm;
        $totalNotPicked = $pickings->where('type', PickingTypeEnum::NOT_PICK)->sum('quantity');

        $outstanding = (float)$deliveryNoteItem->quantity_required - (float)$totalPicked;

        /*
         * Both read as "at least". An item marked as not picked more than once, or holding more in
         * the waiting buckets than is left to do, is still finished: asking for exact equality left
         * it unhandled forever, and the picking screen kept offering the button that overshot it.
         */
        $isFullyPicked        = $outstanding <= self::QUANTITY_TOLERANCE;
        $isMarkedAsUnpickable = (float)$totalNotPicked + (float)$totalWaiting >= $outstanding - self::QUANTITY_TOLERANCE;

        $isCompleted = $isFullyPicked || $isMarkedAsUnpickable;

        // SPECIFIC CONDITION IF SOMEHOW A 0 QUANTITY MANAGED TO GET RECORDED
        if ($deliveryNoteItem->quantity_required == 0 && $pickings->where('type', PickingTypeEnum::NOT_PICK)->isEmpty()) {
            $isCompleted = false;
        }

        if ($deliveryNoteItem->quantity_required > 0) {
            $pickedWeight = $totalPicked * $deliveryNoteItem->estimated_required_weight / $deliveryNoteItem->quantity_required;
        } else {
            $pickedWeight = (int)$totalPicked * $deliveryNoteItem->orgStock->stock->gross_weight;
        }

        $pickedWeight = intval($pickedWeight);

        $dataToUpdate = [
            'quantity_picked'         => $totalPicked,
            'quantity_not_picked'     => $totalNotPicked,
            'is_handled'              => $isCompleted,
            'estimated_picked_weight' => $pickedWeight
        ];

        /*
         * A line goes dirty when its quantity changes under the picker, and a dirty line blocks the
         * whole note. The flag is set on any change, including one that asks for nothing: Faire
         * lowered mxdpk8yece from 8 to 7 with 7 already in the tote, and because a lowering never
         * walks the note back to picking, it sat blocked with no work left to do and re-blocked on
         * every attempt to finish. Clearing it belongs here rather than in StorePicking because
         * this is the one place every path ends up - a pick added, edited or deleted, the rest
         * marked as not picked, or the quantity synced from the marketplace. An over-picked line
         * stays dirty: the trim at the end of picking is what settles that one.
         */
        if ($deliveryNoteItem->is_dirty && $isCompleted && $outstanding >= -self::QUANTITY_TOLERANCE) {
            $dataToUpdate['is_dirty'] = false;
        }

        /** Handle waiting routes only clear the quantities but don't update the state. This will help. */
        if ($deliveryNoteItem->state == DeliveryNoteItemStateEnum::HANDLING_BLOCKED && $totalWaiting == 0) {
            $dataToUpdate['state'] = DeliveryNoteItemStateEnum::HANDLING;
        }

        /*
         * Nobody sets a note as picked inside a picking session, so a line there is picked as soon
         * as it is done: all of it picked or written off, nothing waiting.
         */
        $state          = $dataToUpdate['state'] ?? $deliveryNoteItem->state;
        $isDoneInSession = $isCompleted && $totalWaiting == 0;
        $isPickUndone    = false;
        if ($deliveryNoteItem->picking_session_id && $state == DeliveryNoteItemStateEnum::HANDLING && $isDoneInSession) {
            $dataToUpdate['state'] = DeliveryNoteItemStateEnum::PICKED;
        } elseif ($deliveryNoteItem->picking_session_id && $state == DeliveryNoteItemStateEnum::PICKED && !$isDoneInSession) {
            // A pick undone on a picked line puts it back to be picked.
            $dataToUpdate['state'] = DeliveryNoteItemStateEnum::HANDLING;
            $isPickUndone          = true;
        }

        $deliveryNoteItem = $this->update($deliveryNoteItem, $dataToUpdate);
        $deliveryNoteItem->refresh();

        /*
         * Inside a picking session nobody sets the note as picked, so this is where it learns it is
         * waiting: nothing left to pick on any line, but a line is parked as waiting. Done before
         * the percentages so the picking session sees the note as blocked.
         */
        $deliveryNote = $deliveryNoteItem->deliveryNote;

        // ...and takes its picked note back with it.
        if ($isPickUndone && $deliveryNote->state == DeliveryNoteStateEnum::PICKED) {
            UndoSetAsPickedDeliveryNote::make()->action($deliveryNote, null);
            $deliveryNote->refresh();
        }

        if ($deliveryNote->state == DeliveryNoteStateEnum::HANDLING
            && $deliveryNote->deliveryNoteItems()->where('state', DeliveryNoteItemStateEnum::HANDLING_BLOCKED)->exists()
        ) {
            $hasItemsLeftToPick = $deliveryNote->deliveryNoteItems()
                ->where('state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
                ->get()
                ->contains(fn (DeliveryNoteItem $item) => static::quantityLeftToPick($item) > 0);

            if (!$hasItemsLeftToPick) {
                UpdateDeliveryNoteStateToHandlingBlocked::make()->action($deliveryNote);
            }
        }

        // ...and the note is picked once every line of it is.
        if ($deliveryNoteItem->picking_session_id
            && $deliveryNote->refresh()->state == DeliveryNoteStateEnum::HANDLING
            && !$deliveryNote->deliveryNoteItems()
                ->whereNotIn('state', [DeliveryNoteItemStateEnum::CANCELLED, DeliveryNoteItemStateEnum::PICKED])
                ->exists()
        ) {
            UpdateDeliveryNoteStateToPicked::run($deliveryNote);
        }

        CalculateDeliveryNotePercentage::make()->action($deliveryNoteItem->deliveryNote);

        $this->syncTransactionPickedQuantity($deliveryNoteItem, $deliveryNote);

        return $deliveryNoteItem;
    }

    /**
     * The order's transaction learned what was picked only when the order changed state, so a pick
     * that moves no state - a late pick on a note already blocked - left the transaction reading
     * zero and the order page struck a line that was in the tote (HELP-3235). Only the quantity is
     * written: the amounts stay as the customer submitted them until the picks are final, which is
     * still the order state transition's job.
     */
    protected function syncTransactionPickedQuantity(DeliveryNoteItem $deliveryNoteItem, DeliveryNote $deliveryNote): void
    {
        $transaction = $deliveryNoteItem->transaction;

        if (!$transaction || $transaction->is_follow_on || $transaction->model_type != 'Product') {
            return;
        }

        if (!in_array($transaction->order?->state, [OrderStateEnum::HANDLING, OrderStateEnum::HANDLING_BLOCKED], true)) {
            return;
        }

        $quantityPicked = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction, $deliveryNote)['quantity'];

        if ($transaction->quantity_picked === null || (float)$transaction->quantity_picked !== (float)$quantityPicked) {
            $transaction->update(['quantity_picked' => $quantityPicked]);
        }
    }

    public function action(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, []);

        return $this->handle($deliveryNoteItem);
    }

    public function getCommandSignature(): string
    {
        return 'calculate:delivery_note_item_total_picked {delivery_note_item}';
    }

    public function asCommand($command): int
    {
        $deliveryNoteItem = DeliveryNoteItem::where('id', $command->argument('delivery_note_item'))->firstOrFail();
        $this->handle($deliveryNoteItem);

        return 0;
    }

}
