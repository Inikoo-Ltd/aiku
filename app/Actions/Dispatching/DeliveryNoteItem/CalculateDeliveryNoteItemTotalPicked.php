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
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;

class CalculateDeliveryNoteItemTotalPicked extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;

    public function handle(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        $pickings = $deliveryNoteItem->pickings;


        $totalPicked = $pickings->whereIn('type', [
            PickingTypeEnum::PICK,
            PickingTypeEnum::MAGIC_PICK
        ])->sum('quantity');

        $totalWaiting   = $deliveryNoteItem->quantity_waiting_warehouse + $deliveryNoteItem->quantity_waiting_crm;
        $totalNotPicked = $pickings->where('type', PickingTypeEnum::NOT_PICK)->sum('quantity');

        $isFullyPicked        = $totalPicked == $deliveryNoteItem->quantity_required;
        $isMarkedAsUnpickable = ($totalNotPicked + $totalWaiting) == $deliveryNoteItem->quantity_required - $totalPicked;

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

        print_r($dataToUpdate);

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
