<?php

/*
 * Author: Arya Permana - Kirin
 * Created: Fri, 23 May 2025 11:05:01 Malaysia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNote\CalculateDeliveryNotePercentage;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;

class CalculateDeliveryNoteItemTotalPicked extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;

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

        $deliveryNoteItem = $this->update($deliveryNoteItem, $dataToUpdate);
        $deliveryNoteItem->refresh();

        CalculateDeliveryNotePercentage::make()->action($deliveryNoteItem->deliveryNote);

        return $deliveryNoteItem;
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
