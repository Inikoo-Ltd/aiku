<?php

/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-14h-59m
 * github: https://github.com/KirinZero0
 * copyright 2025
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

        $totalPicked = $pickings->where('type', PickingTypeEnum::PICK)->sum('quantity');
        $totalNotPicked = $pickings->where('type', PickingTypeEnum::NOT_PICK)->sum('quantity');

        $isFullyPicked = ((int) $totalPicked === (int) $deliveryNoteItem->quantity_required);

        $isMarkedAsUnpickable = ((int) $totalNotPicked === (int) $deliveryNoteItem->quantity_required - (int) $totalPicked);

        $isCompleted = $isFullyPicked || $isMarkedAsUnpickable;

        $deliveryNoteItem =  $this->update($deliveryNoteItem, [
            'quantity_picked' => $totalPicked,
            'quantity_not_picked' => $totalNotPicked,
            'is_handled' => $isCompleted,
        ]);

        $deliveryNoteItem->refresh();

        CalculateDeliveryNotePercentage::make()->action($deliveryNoteItem->deliveryNote);

        return $deliveryNoteItem;
    }

    public function action(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, []);

        return $this->handle($deliveryNoteItem);
    }
}
