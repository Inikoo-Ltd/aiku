<?php
/*
 * author Arya Permana - Kirin
 * created on 26-05-2025-16h-16m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;

class CalculateDeliveryNoteItemTotalPacked extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithDeliveryNoteItemNoStrictRules;

    public function handle(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        $totalPacked = $deliveryNoteItem->packings()->sum('quantity');
        $state = $deliveryNoteItem->state;
        
        if ($totalPacked == $deliveryNoteItem->quantity_picked) {
            $state = DeliveryNoteItemStateEnum::PACKED;
        }

        return $this->update($deliveryNoteItem, [
            'quantity_packed' => $totalPacked,
            'state' => $state
        ]);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, []);

        return $this->handle($deliveryNoteItem);
    }
}
